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
 * - Policy `PausesWhileAway` (default, acted): a CLOSED tab freezes the game.
 *   While the page is open the poll requests arrive every few seconds, so the
 *   gap between two ticks stays tiny — an AFK player in front of an open tab
 *   still sees time flow ("tant pis pour lui": pausing is his job). A gap
 *   larger than the grace window can therefore only mean a closed tab or a
 *   sleeping machine: that stretch credits NOTHING and the clock restarts at
 *   the return. The grace also absorbs polling jitter, slow requests and
 *   browser tab-throttling along the way.
 * - Seconds not yet amounting to a full day are carried over (lastTickAt
 *   only advances by the consumed whole days), so no time is lost between
 *   polls.
 *
 * Pure and clock-free: `now` is always injected — the domain never reads
 * the system clock (testability, replayability).
 */
final readonly class TimeProgression
{
    /**
     * Base pace, in real seconds per game day (adjustable; divisible by every
     * speed multiplier). 12 s = 1 day plays a full year in ~73 min at ×1,
     * ~24 min at ×3 — first-playtest balance: 30 s felt far too slow.
     */
    public const int SECONDS_PER_GAME_DAY = 12;

    /**
     * A gap longer than this between two ticks means the tab was closed (open
     * tabs poll every few seconds): that stretch counts as away time and
     * credits nothing. Comfortably above the poll interval — so jitter and
     * background-tab throttling never fake a pause — and small enough that
     * the worst wrongly-credited gap stays anecdotal.
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
