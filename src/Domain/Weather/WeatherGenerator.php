<?php

declare(strict_types=1);

namespace App\Domain\Weather;

use App\Domain\Math\SeasonalCycle;
use App\Domain\Math\SeededNoise;
use App\Domain\Time\GameDate;

use function max;
use function min;
use function round;

/**
 * Deterministic weather generator for Phase 0-1 (game-design §5 layer 2 + §15).
 *
 * Given a game seed and a {@see GameDate}, it produces the day's {@see Weather}
 * as a pure function — same (seed, day) always yields the same weather, so games
 * are reproducible and the model is unit-testable on exact values (no global
 * randomness, no injected clock).
 *
 * - Temperature: a seasonal sinusoid (annual cycle, coldest in mid-January)
 *   plus smooth persistent noise — cold spells and mild spells settle in for
 *   several days — inside a band that widens in winter (air-mass advection)
 *   and narrows in summer.
 * - Cloud cover: smooth value-noise, so cloudy/clear spells persist over
 *   several days, centred on a seasonal mean (cloudier in winter).
 *
 * All coefficients come from {@see WeatherCalibration} (sourced, §13); all
 * randomness from {@see SeededNoise} (deterministic, per-channel).
 */
final readonly class WeatherGenerator
{
    public function __construct(
        private WeatherCalibration $calibration = new WeatherCalibration(),
    ) {
    }

    public function for(int $seed, GameDate $date): Weather
    {
        return new Weather(
            cloudCover: $this->cloudCover($seed, $date),
            temperatureC: $this->temperature($seed, $date),
        );
    }

    private function temperature(int $seed, GameDate $date): float
    {
        $mean = $this->calibration->annualMeanTemperatureC()->value;
        $amplitude = $this->calibration->seasonalTemperatureAmplitudeC()->value;
        $seasonal = $mean - $amplitude * $this->winterCycle($date);

        // Target standard deviation of the day-to-day anomaly: wider in winter
        // (air-mass advection), narrower in summer. These coefficients are the
        // real anomaly std (sourced) — smoothUnit() honours it directly, so the
        // model actually produces cold snaps instead of a flattened ±1 °C wobble.
        $anomalyStd = $this->calibration->dailyTemperatureNoiseC()->value
            + $this->calibration->temperatureNoiseSeasonalAmplitudeC()->value * $this->winterCycle($date);

        // Persistent noise: a cold snap or a mild spell lasts a few days.
        $persistence = max(1, (int) round($this->calibration->temperaturePersistenceDays()->value));
        $noise = SeededNoise::smoothUnit($seed, $date->dayIndex(), 'temp', $persistence) * $anomalyStd;

        return round($seasonal + $noise, 1);
    }

    private function cloudCover(int $seed, GameDate $date): float
    {
        $mean = $this->calibration->annualMeanCloudCover()->value;
        $amplitude = $this->calibration->seasonalCloudAmplitude()->value;
        $spread = $this->calibration->dailyCloudSpread()->value;
        $period = max(1, (int) round($this->calibration->cloudPersistenceDays()->value));

        $noise = SeededNoise::smooth($seed, $date->dayIndex(), 'cloud', $period);

        // Cloudier in winter (+amplitude), clearer in summer (−amplitude).
        $seasonalMean = $mean + $amplitude * $this->winterCycle($date);
        $cloud = $seasonalMean + ($noise - 0.5) * $spread;

        return round($this->clamp01($cloud), 3);
    }

    /**
     * The annual cycle anchored on winter: +1 in the dead of winter (coldest
     * day of the year), −1 in mid-summer.
     */
    private function winterCycle(GameDate $date): float
    {
        return SeasonalCycle::at($date->dayOfYear(), $this->calibration->coldestDayOfYear()->value);
    }

    private function clamp01(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
