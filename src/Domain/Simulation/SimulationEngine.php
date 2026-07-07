<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Building\HeatingEnergyCalculator;
use App\Domain\Building\HeatingNeedCalculator;
use App\Domain\Building\ThermalComfortCalculator;
use App\Domain\Energy\Battery;
use App\Domain\Energy\EnergyBalanceCalculator;
use App\Domain\Energy\EnergyCalibration;
use App\Domain\Energy\EnergyDemandCalculator;
use App\Domain\Energy\SolarProductionCalculator;
use App\Domain\Time\GameDate;
use App\Domain\Weather\WeatherGenerator;

/**
 * The heart of the simulation: turning one day into the next (game-design §3).
 *
 * Pure and deterministic — it wires the weather, energy and building models
 * together and folds a settled day into the game state. No framework, no
 * database, no clock: a game is entirely reproducible from its
 * {@see GameConfig} and the sequence of {@see self::advance()} calls.
 *
 * Heating joins the loop by carrier (game-design §12): a heat pump adds its
 * electricity to the household demand (and thus interacts with solar, battery
 * and grid), while the fuel-oil boiler burns litres outside the electric loop.
 */
final readonly class SimulationEngine
{
    public function __construct(
        private WeatherGenerator $weather = new WeatherGenerator(),
        private SolarProductionCalculator $solar = new SolarProductionCalculator(),
        private EnergyDemandCalculator $baseDemand = new EnergyDemandCalculator(),
        private HeatingNeedCalculator $heatingNeed = new HeatingNeedCalculator(),
        private HeatingEnergyCalculator $heatingEnergy = new HeatingEnergyCalculator(),
        private ThermalComfortCalculator $comfort = new ThermalComfortCalculator(),
        private EnergyBalanceCalculator $balancer = new EnergyBalanceCalculator(),
        private EnergyCalibration $calibration = new EnergyCalibration(),
    ) {
    }

    /**
     * The current day's weather, energy balance, heating and comfort, without
     * advancing the game.
     */
    public function snapshot(GameConfig $config, GameState $state): DailySnapshot
    {
        $household = $state->household;

        $date = GameDate::fromDayIndex($config->epoch, $state->currentDay);
        $weather = $this->weather->for($config->seed, $date);

        $production = $this->solar->dailyProductionKwh($household->solarKwc, $weather, $date);

        $need = $this->heatingNeed->dailyNeedKwh($household->insulation, $weather->temperatureC);
        $heating = $this->heatingEnergy->consumptionFor($household->heatingSystem, $need);

        $demand = $this->baseDemand->dailyDemandKwh($date) + $heating->electricityKwh;
        $balance = $this->balancer->settle($production, $demand, $this->battery($state), $state->batteryLevelKwh);

        $comfort = $this->comfort->comfortFor($household->insulation, $weather->temperatureC);

        return new DailySnapshot($date, $weather, $balance, $heating, $comfort);
    }

    /**
     * Live through the current day and return the resulting next-day state.
     * Once the horizon is reached the state is returned unchanged.
     */
    public function advance(GameConfig $config, GameState $state): GameState
    {
        if ($this->isFinished($config, $state)) {
            return $state;
        }

        return $state->advanced($this->snapshot($config, $state));
    }

    public function isFinished(GameConfig $config, GameState $state): bool
    {
        return $state->currentDay >= $config->horizonDays;
    }

    private function battery(GameState $state): Battery
    {
        return Battery::of($state->household->batteryKwh, $this->calibration->batteryOneWayEfficiency());
    }
}
