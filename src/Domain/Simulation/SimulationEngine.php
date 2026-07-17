<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Building\EmergencyHeatingCalculator;
use App\Domain\Building\HeatingEnergyCalculator;
use App\Domain\Building\HeatingNeedCalculator;
use App\Domain\Building\ThermalComfortCalculator;
use App\Domain\Energy\Battery;
use App\Domain\Energy\EnergyBalanceCalculator;
use App\Domain\Energy\EnergyCalibration;
use App\Domain\Energy\EnergyDemandCalculator;
use App\Domain\Energy\SolarProductionCalculator;
use App\Domain\Finance\BillCalculator;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Scenario\PrimoAccedantScenario;
use App\Domain\Scenario\ScriptedEvent;
use App\Domain\Time\GameDate;
use App\Domain\Weather\WeatherGenerator;

/**
 * The heart of the simulation: turning one day into the next (game-design §3).
 *
 * Pure and deterministic — it wires the weather, energy, building and finance
 * models together and folds a settled day into the game state. No framework,
 * no database, no clock: a game is entirely reproducible from its
 * {@see GameConfig} and the sequence of {@see self::advance()} calls.
 *
 * Heating joins the loop by carrier (game-design §12): a heat pump adds its
 * electricity to the household demand (and thus interacts with solar, battery
 * and grid), while the fuel-oil boiler burns litres outside the electric loop.
 * Money follows the same flows: the day's bill prices the settled energy, and
 * the household's net income lands on the 1st of each month.
 */
final readonly class SimulationEngine
{
    /** @var list<ScriptedEvent> */
    private array $events;

    /**
     * @param list<ScriptedEvent>|null $events scripted events applied after each
     *                                         settled day; null = the Phase 0-1
     *                                         scenario's, [] = none (estimates)
     */
    public function __construct(
        private WeatherGenerator $weather = new WeatherGenerator(),
        private SolarProductionCalculator $solar = new SolarProductionCalculator(),
        private EnergyDemandCalculator $baseDemand = new EnergyDemandCalculator(),
        private HeatingNeedCalculator $heatingNeed = new HeatingNeedCalculator(),
        private HeatingEnergyCalculator $heatingEnergy = new HeatingEnergyCalculator(),
        private EmergencyHeatingCalculator $emergencyHeating = new EmergencyHeatingCalculator(),
        private ThermalComfortCalculator $comfort = new ThermalComfortCalculator(),
        private EnergyBalanceCalculator $balancer = new EnergyBalanceCalculator(),
        private BillCalculator $bill = new BillCalculator(),
        private EnergyCalibration $calibration = new EnergyCalibration(),
        private FinanceCalibration $finance = new FinanceCalibration(),
        ?array $events = null,
    ) {
        $this->events = $events ?? new PrimoAccedantScenario()->events();
    }

    /**
     * The current day's weather, energy balance, heating, comfort and ledger
     * (bill + income), without advancing the game.
     */
    public function snapshot(GameConfig $config, GameState $state): DailySnapshot
    {
        $household = $state->household;

        $date = GameDate::fromDayIndex($config->epoch, $state->currentDay);
        $weather = $this->weather->for($config->seed, $date);

        $production = $this->solar->dailyProductionKwh($household->solarKwc, $weather, $date);
        $baseDemand = $this->baseDemand->dailyDemandKwh($config->seed, $date);

        // A broken boiler forces the emergency electric heaters (not a choice:
        // nobody lives at 4 °C) — Joule heating pours into the electric loop.
        $heating = $household->boilerBroken
            ? $this->emergencyHeating->consumptionFor($household->envelope, $weather->temperatureC, $baseDemand)
            : $this->heatingEnergy->consumptionFor(
                $household->heatingSystem,
                $this->heatingNeed->dailyNeedKwh($household->envelope, $weather->temperatureC, $household->heatingSetpointC),
                $household->lowTempEmitters,
            );

        $demand = $baseDemand + $heating->electricityKwh;
        $balance = $this->balancer->settle($production, $demand, $this->battery($state), $state->batteryLevelKwh);

        // While broken, ALL indoor electricity ends up as heat (appliances +
        // emergency heaters): the house sits at that equilibrium, at best the
        // survival setpoint, lower on cold days when the heaters are maxed out.
        $comfort = $household->boilerBroken
            ? $this->comfort->unheatedComfortFor($household->envelope, $weather->temperatureC, $baseDemand + $heating->electricityKwh)
            : $this->comfort->comfortFor($household->envelope, $weather->temperatureC, $household->heatingSetpointC);

        return new DailySnapshot(
            $date,
            $weather,
            $balance,
            $heating,
            $comfort,
            $this->bill->billFor($balance, $heating),
            $this->incomeFor($date),
            $date->isFirstOfMonth() ? $state->loan->installmentDue() : Money::zero(),
        );
    }

    /**
     * Net monthly income (income minus non-energy living expenses), credited
     * on the 1st of each month; zero on every other day.
     */
    private function incomeFor(GameDate $date): Money
    {
        if (!$date->isFirstOfMonth()) {
            return Money::zero();
        }

        return Money::fromEuros(
            $this->finance->monthlyNetIncome()->value - $this->finance->monthlyLivingExpenses()->value,
        );
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

        return $this->withScriptedEvents($config, $state->advanced($this->snapshot($config, $state)));
    }

    /**
     * Applies the scenario's scripted events to the settled morning. The
     * engine knows nothing about what an event does or when it triggers —
     * the scenario does (game-design §15;
     * e.g. {@see \App\Domain\Scenario\BoilerBreakdownEvent}).
     */
    private function withScriptedEvents(GameConfig $config, GameState $state): GameState
    {
        foreach ($this->events as $event) {
            if ($event->shouldFire($config, $state)) {
                $state = $event->fire($state);
            }
        }

        return $state;
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
