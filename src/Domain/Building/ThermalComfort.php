<?php

declare(strict_types=1);

namespace App\Domain\Building;

use InvalidArgumentException;

/**
 * The thermal-comfort outcome of one day (game-design §8).
 *
 * The felt temperature can sit below the indoor air temperature: poorly
 * insulated walls radiate cold, so a passoire at 19 °C of air can feel like
 * 16 °C. The score (0-100) degrades progressively as the felt temperature
 * leaves the comfort range — never a cliff.
 */
final readonly class ThermalComfort
{
    public function __construct(
        /** Indoor air temperature, in °C. */
        public float $indoorC,
        /** Felt temperature (air minus cold-wall effect), in °C. */
        public float $feltC,
        /** Comfort score, 100 (comfortable) down to 0. */
        public int $score,
    ) {
        if ($score < 0 || $score > 100) {
            throw new InvalidArgumentException("Comfort score must be within [0, 100], got {$score}.");
        }
    }
}
