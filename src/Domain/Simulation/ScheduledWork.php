<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

/**
 * A renovation ordered but not yet applied: the chantier is scheduled, its
 * effect on the household lands at {@see self::$completionDay} (game-design §1,
 * the « délai » access-cost lever — a work is no longer instantaneous).
 *
 * Two dates carry the chantier window: the artisan only shows up at
 * {@see self::$chantierStartDay} (after the lead time, which can be months),
 * and the work is done at {@see self::$completionDay}. The scene draws the
 * "travaux" marker only inside that window — never from the order, when there
 * is nothing to see on the house yet.
 *
 * Identified by the work's catalogue slug; the money was already committed at
 * order time (this only defers the household effect).
 */
final readonly class ScheduledWork
{
    public function __construct(
        public string $workSlug,
        /** @var int<0, max> */
        public int $chantierStartDay,
        /** @var int<0, max> */
        public int $completionDay,
    ) {
    }
}
