<?php

declare(strict_types=1);

namespace App\Domain\Time;

/**
 * The outcome of accounting for elapsed real time: how many game days are due
 * now, and the progression to carry forward (remainder preserved).
 */
final readonly class TickResult
{
    public function __construct(
        /** @var int<0, max> */
        public int $days,
        public TimeProgression $progression,
    ) {
    }
}
