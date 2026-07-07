<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Building\Household;

/**
 * The mutable-over-time state of a game, as an immutable snapshot.
 *
 * Everything that changes as the game is played lives here: the current day
 * (tick counter), the household configuration (equipment, insulation, heating
 * — the player's decisions, game-design §8/§18), the battery charge carried
 * from day to day, and the running totals. Advancing a day is a pure
 * transition {@see self::advanced()} returning a new state — the simulation
 * core never mutates in place (game-design §3).
 */
final readonly class GameState
{
    /**
     * @param int<0, max> $currentDay
     */
    public function __construct(
        public int $currentDay,
        public Household $household,
        public float $batteryLevelKwh,
        public PeriodTotals $totals,
    ) {
    }

    public static function start(Household $household): self
    {
        return new self(0, $household, 0.0, new PeriodTotals());
    }

    /**
     * The state after living through one settled day. The household carries
     * over unchanged — renovations/installations become explicit player
     * actions in a later step.
     */
    public function advanced(DailySnapshot $day): self
    {
        return new self(
            $this->currentDay + 1,
            $this->household,
            $day->balance->batteryLevelKwh,
            $this->totals->add($day->balance, $day->heating->fuelOilLitres, $day->comfort->score),
        );
    }
}
