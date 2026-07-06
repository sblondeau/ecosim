<?php

declare(strict_types=1);

namespace App\Domain\Weather;

use InvalidArgumentException;

/**
 * The weather on a single game day (Phase 0-1 scope: nébulosité + température).
 *
 * Deliberately limited to the two parameters the MVP needs — cloud cover (which
 * will drive solar production) and daily-mean air temperature (which will drive
 * heating demand and thermal comfort). No wind, pressure or extreme events at
 * this stage (game-design §15).
 */
final readonly class Weather
{
    /**
     * @param float $cloudCover   overcast fraction, 0 (clear sky) to 1 (fully overcast)
     * @param float $temperatureC daily-mean air temperature, in °C
     */
    public function __construct(
        public float $cloudCover,
        public float $temperatureC,
    ) {
        if ($cloudCover < 0.0 || $cloudCover > 1.0) {
            throw new InvalidArgumentException("Cloud cover must be within [0, 1], got {$cloudCover}.");
        }
    }
}
