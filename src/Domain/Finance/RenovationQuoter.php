<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
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
            Renovation::Insulation => $this->insulationQuote($household),
            Renovation::HeatPump => $this->heatPumpQuote($household),
            Renovation::SolarPanels => $this->solarQuote($household),
            Renovation::HomeBattery => $this->batteryQuote($household),
        };
    }

    private function insulationQuote(Household $household): ?RenovationQuote
    {
        [$cost, $target] = match ($household->insulation) {
            InsulationLevel::Original => [
                $this->calibration->insulationRetrofitCost(),
                InsulationLevel::Retrofitted,
            ],
            InsulationLevel::Retrofitted => [
                $this->calibration->insulationReinforceCost(),
                InsulationLevel::Reinforced,
            ],
            InsulationLevel::Reinforced => [null, null],
        };

        if (null === $cost || null === $target) {
            return null;
        }

        $price = Money::fromEuros($cost->value);

        return new RenovationQuote(
            work: Renovation::Insulation,
            title: sprintf('Isolation « %s »', $target->label()),
            cost: $price,
            subsidy: $this->subsidy->subsidyFor($price),
            resultingHousehold: $household->withInsulation($target),
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
        if ($household->batteryKwh > 0.0) {
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
}
