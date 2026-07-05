<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Energy\EnergyBalance;

/**
 * The mutable-over-time state of a game, as an immutable snapshot.
 *
 * Everything that changes as the game is played lives here: the current day
 * (tick counter), the battery charge carried from day to day, and the running
 * totals. Advancing a day is a pure transition {@see self::advanced()} returning
 * a new state — the simulation core never mutates in place (game-design §3).
 */
final readonly class GameState
{
    /**
     * @param int<0, max> $currentDay
     */
    public function __construct(
        public int $currentDay,
        public float $batteryLevelKwh,
        public PeriodTotals $totals,
    ) {
    }

    public static function start(): self
    {
        return new self(0, 0.0, new PeriodTotals());
    }

    /**
     * The state after living through one settled day.
     */
    public function advanced(float $batteryLevelKwh, EnergyBalance $balance): self
    {
        return new self(
            $this->currentDay + 1,
            $batteryLevelKwh,
            $this->totals->add($balance),
        );
    }
}
