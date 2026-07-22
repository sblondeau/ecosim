<?php

declare(strict_types=1);

namespace App\Domain\Scenario;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Simulation\GameState;

/**
 * The single locked Phase 0-1 scenario (game-design §15, §18): a primo-accédant
 * couple in an old ~100 m² fuel-oil house, original insulation, DPE G, and NO
 * production equipment — installing solar, a battery, insulation or a heat
 * pump are the game's decisions.
 *
 * One place defines where the game starts and what is scripted to happen, so
 * the store that creates games, the engine that fires the events and the end
 * report that measures the journey against day 0 can never drift apart.
 */
final readonly class PrimoAccedantScenario implements Scenario
{
    /** Fixed horizon: one full year, then the factual end report (§15). */
    public const int HORIZON_DAYS = 365;

    /**
     * Day index of the scripted boiler breakdown: with the standard January 1st
     * epoch, day 19 is January 20th — deep in the first winter, early enough
     * that the whole game measures the consequences of the player's answer.
     */
    public const int BOILER_BREAKDOWN_DAY = 19;

    public function __construct(
        private FinanceCalibration $finance = new FinanceCalibration(),
    ) {
    }

    public function initialState(): GameState
    {
        return GameState::start($this->initialHousehold(), $this->startingSavings());
    }

    public function horizonDays(): int
    {
        return self::HORIZON_DAYS;
    }

    public function events(): array
    {
        return [new BoilerBreakdownEvent(self::BOILER_BREAKDOWN_DAY)];
    }

    public function explainedEvents(): array
    {
        return [
            new ScenarioIntroEvent(),
            new ScenarioBriefingEvent(),
            new BoilerBreakdownEvent(self::BOILER_BREAKDOWN_DAY),
        ];
    }

    public function initialHousehold(): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(false, WallInsulation::None, Glazing::Single),
            heatingSystem: HeatingSystem::FuelOilBoiler,
        );
    }

    public function startingSavings(): Money
    {
        return Money::fromEuros($this->finance->startingSavings()->value);
    }
}
