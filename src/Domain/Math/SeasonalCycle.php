<?php

declare(strict_types=1);

namespace App\Domain\Math;

use function cos;

use const M_PI;

/**
 * The annual seasonal cycle as a cosine, shared by every seasonal quantity
 * (temperature, cloud cover, solar potential, demand).
 *
 * {@see self::cosine()} returns +1 at the given peak day of the year and −1
 * half a year later, so a quantity that peaks on day D is written
 * `mean + amplitude * SeasonalCycle::cosine(dayOfYear, D)`.
 */
final class SeasonalCycle
{
    private const float DAYS_PER_YEAR = 365.25;

    /**
     * @param int<1, 366> $dayOfYear
     */
    public static function cosine(int $dayOfYear, float $peakDayOfYear): float
    {
        return cos(2.0 * M_PI * ($dayOfYear - $peakDayOfYear) / self::DAYS_PER_YEAR);
    }
}
