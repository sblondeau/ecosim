<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * A chantier's two durations, in game-days: the lead time before the artisan
 * shows up (contact, devis, carnet de commandes) and the pose itself. Split so
 * the scene can draw the "travaux" marker only during the pose window
 * ({@see \App\Domain\Simulation\ScheduledWork}), not for the months of lead.
 */
final readonly class ChantierDelay
{
    public function __construct(
        /** @var int<0, max> */
        public int $leadDays,
        /** @var int<0, max> */
        public int $buildDays,
    ) {
    }

    /** @return int<0, max> */
    public function totalDays(): int
    {
        return $this->leadDays + $this->buildDays;
    }
}
