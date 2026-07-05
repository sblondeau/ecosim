<?php

declare(strict_types=1);

namespace App\Domain\Calibration;

use InvalidArgumentException;

/**
 * A single calibrated coefficient, traceable to a source (game-design §13).
 *
 * The rule for EcoSim: no gameplay number without a citable source and an
 * uncertainty range. This value object is that contract in code — a coefficient
 * carries its value, unit, plausible range, source and review date, so every
 * number used by the simulation can be audited back to where it came from.
 *
 * The {@see self::value} is the point estimate the simulation uses; the range
 * (min/max) documents the uncertainty of the literature, not a runtime range.
 */
final readonly class Coefficient
{
    public function __construct(
        public float $value,
        public string $unit,
        public float $min,
        public float $max,
        public string $source,
        /** ISO-8601 date (Y-m-d) of the last review of this value. */
        public string $reviewedOn,
        public string $note = '',
    ) {
        if ($min > $max) {
            throw new InvalidArgumentException("Coefficient min ({$min}) cannot exceed max ({$max}).");
        }

        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("Coefficient value ({$value}) must lie within [{$min}, {$max}].");
        }
    }
}
