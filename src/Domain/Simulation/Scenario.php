<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;

/**
 * The single locked Phase 0-1 scenario (game-design §15, §18): a primo-accédant
 * couple in an old ~100 m² fuel-oil house, original insulation, DPE G, and NO
 * production equipment — installing solar, a battery, insulation or a heat
 * pump are the game's decisions.
 *
 * One place defines where a game starts and which scripted events it holds, so
 * the store that creates games and the end report that measures the journey
 * against day 0 can never drift apart.
 */
final readonly class Scenario
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

    public function initialHousehold(): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            insulation: InsulationLevel::Original,
            heatingSystem: HeatingSystem::FuelOilBoiler,
        );
    }

    public function startingSavings(): Money
    {
        return Money::fromEuros($this->finance->startingSavings()->value);
    }

    public function initialState(): GameState
    {
        return GameState::start($this->initialHousehold(), $this->startingSavings());
    }
}
