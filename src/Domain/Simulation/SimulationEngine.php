<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

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
 * Pure and deterministic — it wires the weather and energy models together and
 * folds a settled day into the game state. No framework, no database, no clock:
 * a game is entirely reproducible from its {@see GameConfig} and the sequence of
 * {@see self::advance()} calls.
 */
final readonly class SimulationEngine
{
    public function __construct(
        private WeatherGenerator $weather = new WeatherGenerator(),
        private SolarProductionCalculator $solar = new SolarProductionCalculator(),
        private EnergyDemandCalculator $demand = new EnergyDemandCalculator(),
        private EnergyBalanceCalculator $balancer = new EnergyBalanceCalculator(),
        private EnergyCalibration $calibration = new EnergyCalibration(),
    ) {
    }

    /**
     * The current day's weather and energy balance, without advancing the game.
     */
    public function snapshot(GameConfig $config, GameState $state): DailySnapshot
    {
        $date = GameDate::fromDayIndex($config->epoch, $state->currentDay);
        $weather = $this->weather->for($config->seed, $date);
        $production = $this->solar->dailyProductionKwh($state->solarKwc, $weather, $date);
        $demand = $this->demand->dailyDemandKwh($date);
        $balance = $this->balancer->settle($production, $demand, $this->battery($state), $state->batteryLevelKwh);

        return new DailySnapshot($date, $weather, $balance);
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

        $snapshot = $this->snapshot($config, $state);

        return $state->advanced($snapshot->balance->batteryLevelKwh, $snapshot->balance);
    }

    public function isFinished(GameConfig $config, GameState $state): bool
    {
        return $state->currentDay >= $config->horizonDays;
    }

    private function battery(GameState $state): Battery
    {
        return Battery::of($state->batteryKwh, $this->calibration->batteryOneWayEfficiency());
    }
}
