<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\Glazing;
use App\Domain\Building\Household;
use App\Domain\Building\Ventilation;
use App\Domain\Building\WallInsulation;
use App\Domain\Energy\EnergyCalibration;
use LogicException;

use function sprintf;

/**
 * Prices a renovation for a given household: cost (sourced), prime, and the
 * resulting household. Returns null when the work does not apply (already
 * done, nothing left to upgrade) — the UI simply hides the action.
 *
 * Works are instantaneous in Phase 0-1 (no permitting/construction delays yet
 * — the real « délai » access-cost lever arrives with later phases).
 */
final readonly class RenovationQuoter
{
    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
        private SubsidyCalculator $subsidy = new SubsidyCalculator(),
        private EnergyCalibration $energy = new EnergyCalibration(),
        private RenovationCatalog $catalog = new RenovationCatalog(),
    ) {
    }

    public function quote(Renovation $work, Household $household): ?RenovationQuote
    {
        // Bridge, while works migrate one by one: a definition wins over the
        // legacy match. The match shrinks at each batch and dies in task 6.
        $definition = $this->catalog->tryGet($work->value);
        if (null !== $definition) {
            return $this->fromDefinition($work, $definition, $household);
        }

        return match ($work) {
            Renovation::RoofInsulation => $this->roofQuote($household),
            Renovation::WallInsulationInterior => $this->wallQuote($household, WallInsulation::Interior, Renovation::WallInsulationInterior, 'Isolation des murs — intérieure (ITI)', $this->calibration->wallInsulationInteriorCost()->value),
            Renovation::WallInsulationExterior => $this->wallQuote($household, WallInsulation::Exterior, Renovation::WallInsulationExterior, 'Isolation des murs — extérieure (ITE)', $this->calibration->wallInsulationExteriorCost()->value),
            Renovation::Glazing => $this->glazingQuote($household),
            Renovation::SolarKit => $this->solarKitQuote($household),
            Renovation::SolarPanels => $this->solarQuote($household),
            Renovation::HomeBattery => $this->batteryQuote($household),
            Renovation::VentilationDoubleFlow => $this->ventilationQuote($household),
            Renovation::DraughtProofing => $this->draughtProofingQuote($household),
            Renovation::ThermalCurtains => $this->thermalCurtainsQuote($household),
            // Migrated to the catalogue (tasks 3-5): a definition always
            // answers these before the match is reached. Reaching here would
            // mean defaultWorks() lost an entry — a real bug, not a legal state.
            default => throw new LogicException(sprintf('"%s" is migrated to the renovation catalogue — the bridge above should have answered it.', $work->value)),
        };
    }

    /**
     * Turns a work's own offer into a signable quote by applying the FINANCING
     * POLICY — the prime perimeter and rate. That policy is identical for every
     * work, which is exactly why definitions declare offers and not quotes.
     */
    private function fromDefinition(Renovation $work, RenovationDefinition $definition, Household $household): ?RenovationQuote
    {
        $offer = $definition->offerFor($household);
        if (null === $offer) {
            return null;
        }

        return new RenovationQuote(
            work: $work,
            title: $offer->title,
            cost: $offer->cost,
            subsidy: $definition->isEnergyPerformanceWork()
                ? $this->subsidy->subsidyFor($offer->cost)
                : Money::zero(),
            resultingHousehold: $offer->resultingHousehold,
        );
    }

    private function roofQuote(Household $household): ?RenovationQuote
    {
        if ($household->envelope->roofInsulated) {
            return null;
        }
        $price = Money::fromEuros($this->calibration->roofInsulationCost()->value);

        return new RenovationQuote(
            work: Renovation::RoofInsulation,
            title: 'Isolation des combles',
            cost: $price,
            subsidy: $this->subsidy->subsidyFor($price),
            resultingHousehold: $household->withEnvelope($household->envelope->withRoofInsulated(true)),
        );
    }

    private function wallQuote(Household $household, WallInsulation $target, Renovation $work, string $title, float $cost): ?RenovationQuote
    {
        // ITI et ITE mutuellement exclusifs : dès que les murs sont isolés, plus d'offre murs.
        if (WallInsulation::None !== $household->envelope->walls) {
            return null;
        }
        $price = Money::fromEuros($cost);

        return new RenovationQuote(
            work: $work,
            title: $title,
            cost: $price,
            subsidy: $this->subsidy->subsidyFor($price),
            resultingHousehold: $household->withEnvelope($household->envelope->withWalls($target)),
        );
    }

    private function glazingQuote(Household $household): ?RenovationQuote
    {
        $target = match ($household->envelope->glazing) {
            Glazing::Single => Glazing::Double,
            Glazing::Double => Glazing::Triple,
            Glazing::Triple => null,
        };
        if (null === $target) {
            return null;
        }
        $price = Money::fromEuros($this->calibration->glazingUpgradeCost()->value);

        return new RenovationQuote(
            work: Renovation::Glazing,
            title: sprintf('Menuiseries — %s', $target->label()),
            cost: $price,
            subsidy: $this->subsidy->subsidyFor($price),
            resultingHousehold: $household->withEnvelope($household->envelope->withGlazing($target)),
        );
    }

    /**
     * The plug-and-play kit — no installer, no aid — is the cheap entry
     * point: available on a bare roof only, superseded by the full install.
     */
    private function solarKitQuote(Household $household): ?RenovationQuote
    {
        if (0.0 !== $household->solarKwc) {
            return null;
        }

        $kwc = $this->energy->solarKitPeakPowerKwc()->value;

        return new RenovationQuote(
            work: Renovation::SolarKit,
            title: sprintf('Kit solaire plug-and-play %.1f kWc', $kwc),
            cost: Money::fromEuros($this->calibration->solarKitInstallCost()->value),
            subsidy: Money::zero(),
            resultingHousehold: $household->withSolarKwc($kwc),
        );
    }

    private function solarQuote(Household $household): ?RenovationQuote
    {
        // The gate is the full install's own power, not zero: this also
        // offers the full install as an upgrade from the plug-and-play kit.
        if ($household->solarKwc >= $this->energy->defaultSolarPeakPowerKwc()->value) {
            return null;
        }

        $kwc = $this->energy->defaultSolarPeakPowerKwc()->value;

        return new RenovationQuote(
            work: Renovation::SolarPanels,
            title: sprintf('Panneaux solaires %.0f kWc', $kwc),
            cost: Money::fromEuros($this->calibration->solarInstallCost()->value),
            subsidy: Money::zero(),
            resultingHousehold: $household->withSolarKwc($kwc),
        );
    }

    private function batteryQuote(Household $household): ?RenovationQuote
    {
        // A battery only stores solar production (the MVP's sole source) —
        // offering it before panels are installed would let it sit unused.
        if ($household->batteryKwh > 0.0 || $household->solarKwc <= 0.0) {
            return null;
        }

        $kwh = $this->energy->defaultBatteryCapacityKwh()->value;

        return new RenovationQuote(
            work: Renovation::HomeBattery,
            title: sprintf('Batterie domestique %.0f kWh', $kwh),
            cost: Money::fromEuros($this->calibration->batteryInstallCost()->value),
            subsidy: Money::zero(),
            resultingHousehold: $household->withBatteryKwh($kwh),
        );
    }

    private function ventilationQuote(Household $household): ?RenovationQuote
    {
        if (Ventilation::None !== $household->envelope->ventilation) {
            return null;
        }
        $price = Money::fromEuros($this->calibration->ventilationDoubleFlowCost()->value);

        return new RenovationQuote(
            work: Renovation::VentilationDoubleFlow,
            title: 'VMC double flux',
            cost: $price,
            subsidy: $this->subsidy->subsidyFor($price),
            resultingHousehold: $household->withEnvelope($household->envelope->withVentilation(Ventilation::DoubleFlow)),
        );
    }

    private function draughtProofingQuote(Household $household): ?RenovationQuote
    {
        if ($household->envelope->draughtProofed) {
            return null;
        }
        $price = Money::fromEuros($this->calibration->draughtProofingCost()->value);

        return new RenovationQuote(
            work: Renovation::DraughtProofing,
            title: 'Calfeutrage / joints',
            cost: $price,
            subsidy: Money::zero(),
            resultingHousehold: $household->withEnvelope($household->envelope->withDraughtProofed(true)),
        );
    }

    private function thermalCurtainsQuote(Household $household): ?RenovationQuote
    {
        if ($household->envelope->thermalCurtains) {
            return null;
        }
        $price = Money::fromEuros($this->calibration->thermalCurtainsCost()->value);

        return new RenovationQuote(
            work: Renovation::ThermalCurtains,
            title: 'Rideaux thermiques',
            cost: $price,
            subsidy: Money::zero(),
            resultingHousehold: $household->withEnvelope($household->envelope->withThermalCurtains(true)),
        );
    }
}
