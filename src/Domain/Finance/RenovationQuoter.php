<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use App\Domain\Energy\EnergyCalibration;

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
    ) {
    }

    public function quote(Renovation $work, Household $household): ?RenovationQuote
    {
        return match ($work) {
            Renovation::RoofInsulation => $this->roofQuote($household),
            Renovation::WallInsulationInterior => $this->wallQuote($household, WallInsulation::Interior, Renovation::WallInsulationInterior, 'Isolation des murs — intérieure (ITI)', $this->calibration->wallInsulationInteriorCost()->value),
            Renovation::WallInsulationExterior => $this->wallQuote($household, WallInsulation::Exterior, Renovation::WallInsulationExterior, 'Isolation des murs — extérieure (ITE)', $this->calibration->wallInsulationExteriorCost()->value),
            Renovation::Glazing => $this->glazingQuote($household),
            Renovation::HeatPump => $this->heatPumpQuote($household),
            Renovation::SolarPanels => $this->solarQuote($household),
            Renovation::HomeBattery => $this->batteryQuote($household),
            Renovation::BoilerRepair => $this->boilerRepairQuote($household),
            Renovation::LowTempEmitters => $this->lowTempEmittersQuote($household),
            Renovation::PelletBoiler => $this->pelletBoilerQuote($household),
        };
    }

    private function boilerRepairQuote(Household $household): ?RenovationQuote
    {
        if (!$household->boilerBroken) {
            return null;
        }

        return new RenovationQuote(
            work: Renovation::BoilerRepair,
            title: 'Réparer la chaudière fioul',
            cost: Money::fromEuros($this->calibration->boilerRepairCost()->value),
            subsidy: Money::zero(),
            resultingHousehold: $household->withBoilerBroken(false),
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

    private function heatPumpQuote(Household $household): ?RenovationQuote
    {
        if (HeatingSystem::HeatPump === $household->heatingSystem) {
            return null;
        }

        $price = Money::fromEuros($this->calibration->heatPumpInstallCost()->value);

        return new RenovationQuote(
            work: Renovation::HeatPump,
            title: 'Pompe à chaleur air/eau',
            cost: $price,
            subsidy: $this->subsidy->subsidyFor($price),
            resultingHousehold: $household->withHeatingSystem(HeatingSystem::HeatPump),
        );
    }

    private function solarQuote(Household $household): ?RenovationQuote
    {
        if ($household->solarKwc > 0.0) {
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

    private function lowTempEmittersQuote(Household $household): ?RenovationQuote
    {
        if ($household->lowTempEmitters) {
            return null;
        }
        $price = Money::fromEuros($this->calibration->lowTempEmittersCost()->value);

        return new RenovationQuote(
            work: Renovation::LowTempEmitters,
            title: 'Émetteurs basse température',
            cost: $price,
            subsidy: $this->subsidy->subsidyFor($price),
            resultingHousehold: $household->withLowTempEmitters(true),
        );
    }

    private function pelletBoilerQuote(Household $household): ?RenovationQuote
    {
        if (HeatingSystem::PelletBoiler === $household->heatingSystem) {
            return null;
        }
        $price = Money::fromEuros($this->calibration->pelletBoilerCost()->value);

        return new RenovationQuote(
            work: Renovation::PelletBoiler,
            title: 'Chaudière à granulés',
            cost: $price,
            subsidy: $this->subsidy->subsidyFor($price),
            resultingHousehold: $household->withHeatingSystem(HeatingSystem::PelletBoiler),
        );
    }
}
