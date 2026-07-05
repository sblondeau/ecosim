<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Energy\EnergyBalance;
use InvalidArgumentException;

/**
 * The mutable-over-time state of a game, as an immutable snapshot.
 *
 * Everything that changes as the game is played lives here: the current day
 * (tick counter), the installed equipment (solar, battery — the player can
 * install/change these as a game action, game-design §8/§18), the battery
 * charge carried from day to day, and the running totals. Advancing a day is
 * a pure transition {@see self::advanced()} returning a new state — the
 * simulation core never mutates in place (game-design §3).
 */
final readonly class GameState
{
    /**
     * @param int<0, max> $currentDay
     * @param float       $solarKwc   installed solar peak power (0 = none)
     * @param float       $batteryKwh battery usable capacity (0 = none)
     */
    public function __construct(
        public int $currentDay,
        public float $solarKwc,
        public float $batteryKwh,
        public float $batteryLevelKwh,
        public PeriodTotals $totals,
    ) {
        if ($solarKwc < 0.0) {
            throw new InvalidArgumentException("Solar power cannot be negative: {$solarKwc}.");
        }

        if ($batteryKwh < 0.0) {
            throw new InvalidArgumentException("Battery capacity cannot be negative: {$batteryKwh}.");
        }
    }

    public static function start(float $solarKwc, float $batteryKwh): self
    {
        return new self(0, $solarKwc, $batteryKwh, 0.0, new PeriodTotals());
    }

    /**
     * The state after living through one settled day. Installed equipment
     * carries over unchanged — nothing in Phase 0-1 yet lets the player
     * install/upgrade it mid-game.
     */
    public function advanced(float $batteryLevelKwh, EnergyBalance $balance): self
    {
        return new self(
            $this->currentDay + 1,
            $this->solarKwc,
            $this->batteryKwh,
            $batteryLevelKwh,
            $this->totals->add($balance),
        );
    }
}
