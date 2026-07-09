<?php

declare(strict_types=1);

namespace App\Domain\Time;

use DateTimeImmutable;

use function intdiv;
use function sprintf;

/**
 * Maps elapsed REAL time onto due game days (the acted tick decision: the
 * engine is decoupled from its trigger; progression is based on wall-clock
 * time actually spent in front of the game).
 *
 * - Base pace: 1 game day per {@see self::SECONDS_PER_GAME_DAY} real seconds,
 *   multiplied by the player's {@see TickSpeed} (×2, ×3 — the constant is
 *   divisible by every multiplier so a day is a whole number of seconds).
 * - Policy `PausesWhileAway` (default, acted): real time only counts while
 *   the game is being watched. An absence longer than the grace window
 *   credits NOTHING — the clock simply restarts at the return. The grace
 *   absorbs normal polling jitter and short tab-switches.
 * - Seconds not yet amounting to a full day are carried over (lastTickAt
 *   only advances by the consumed whole days), so no time is lost between
 *   polls.
 *
 * Pure and clock-free: `now` is always injected — the domain never reads
 * the system clock (testability, replayability).
 */
final readonly class TimeProgression
{
    /** Base pace, in real seconds per game day (~30 s = 1 day, adjustable). */
    public const int SECONDS_PER_GAME_DAY = 30;

    /**
     * Absences longer than this credit no game time (PausesWhileAway).
     * Comfortably above the dashboard poll interval, well below a coffee break.
     */
    public const int AWAY_GRACE_SECONDS = 90;

    public function __construct(
        /** The real moment up to which game time has been accounted for. */
        public DateTimeImmutable $lastTickAt,
        public TickSpeed $speed,
    ) {
    }

    public static function startingAt(DateTimeImmutable $now): self
    {
        return new self($now, TickSpeed::Normal);
    }

    /**
     * Accounts for the real time elapsed until $now: how many game days are
     * due, and the progression that carries the unconsumed remainder.
     */
    public function tick(DateTimeImmutable $now): TickResult
    {
        $elapsed = $now->getTimestamp() - $this->lastTickAt->getTimestamp();

        if (TickSpeed::Paused === $this->speed || $elapsed > self::AWAY_GRACE_SECONDS) {
            // Paused, or away: no time credited, the clock restarts here.
            return new TickResult(0, new self($now, $this->speed));
        }

        if ($elapsed <= 0) {
            return new TickResult(0, $this);
        }

        $secondsPerDay = intdiv(self::SECONDS_PER_GAME_DAY, $this->speed->multiplier());
        $days = intdiv($elapsed, $secondsPerDay);

        if ($days < 1) {
            return new TickResult(0, $this);
        }

        return new TickResult(
            $days,
            new self(
                $this->lastTickAt->modify(sprintf('+%d seconds', $days * $secondsPerDay)),
                $this->speed,
            ),
        );
    }

    /**
     * Changing pace restarts the clock at $now: the partial day in progress
     * is dropped rather than converted across speeds (at most a few real
     * seconds — simplicity over bookkeeping).
     */
    public function withSpeed(TickSpeed $speed, DateTimeImmutable $now): self
    {
        return new self($now, $speed);
    }
}
