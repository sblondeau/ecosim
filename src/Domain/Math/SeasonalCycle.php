<?php

declare(strict_types=1);

namespace App\Domain\Math;

use function cos;

use const M_PI;

/**
 * The annual seasonal cycle, shared by every seasonal quantity (temperature,
 * cloud cover, solar potential, demand).
 *
 * {@see self::at()} returns the cycle's value on a given day: +1 on the peak
 * day of the year and −1 half a year later, so a quantity that peaks on day D
 * is written `mean + amplitude * SeasonalCycle::at(dayOfYear, D)`.
 */
final class SeasonalCycle
{
    private const float DAYS_PER_YEAR = 365.25;

    /**
     * @param int<1, 366> $dayOfYear
     */
    public static function at(int $dayOfYear, float $peakDayOfYear): float
    {
        return cos(2.0 * M_PI * ($dayOfYear - $peakDayOfYear) / self::DAYS_PER_YEAR);
    }
}
