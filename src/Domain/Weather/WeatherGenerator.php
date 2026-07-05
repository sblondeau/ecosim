<?php

declare(strict_types=1);

namespace App\Domain\Weather;

use App\Domain\Math\SeasonalCycle;
use App\Domain\Time\GameDate;

/**
 * Deterministic weather generator for Phase 0-1 (game-design §5 layer 2 + §15).
 *
 * Given a game seed and a {@see GameDate}, it produces the day's {@see Weather}
 * as a pure function — same (seed, day) always yields the same weather, so games
 * are reproducible and the model is unit-testable on exact values (no global
 * randomness, no injected clock).
 *
 * - Temperature: a seasonal sinusoid (annual cycle, coldest in mid-January) plus
 *   a bounded day-to-day noise term.
 * - Cloud cover: smooth value-noise interpolated between per-control-point random
 *   values, so cloudy/clear spells persist over several days, centred on a
 *   seasonal mean (cloudier in winter).
 *
 * All coefficients come from {@see WeatherCalibration} (sourced, §13).
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
        $noiseBand = $this->calibration->dailyTemperatureNoiseC()->value;

        $seasonal = $mean - $amplitude * $this->seasonalCosine($date);
        $noise = ($this->hash01($seed, $date->dayIndex(), 'temp') - 0.5) * 2.0 * $noiseBand;

        return round($seasonal + $noise, 1);
    }

    private function cloudCover(int $seed, GameDate $date): float
    {
        $mean = $this->calibration->annualMeanCloudCover()->value;
        $amplitude = $this->calibration->seasonalCloudAmplitude()->value;
        $spread = $this->calibration->dailyCloudSpread()->value;
        $period = max(1, (int) round($this->calibration->cloudPersistenceDays()->value));

        $day = $date->dayIndex();
        $controlPoint = intdiv($day, $period);
        $t = (float) ($day - $controlPoint * $period) / $period;

        $low = $this->hash01($seed, $controlPoint, 'cloud');
        $high = $this->hash01($seed, $controlPoint + 1, 'cloud');
        $noise = $this->lerp($low, $high, $this->smoothstep($t));

        // Cloudier in winter (+amplitude), clearer in summer (−amplitude).
        $seasonalMean = $mean + $amplitude * $this->seasonalCosine($date);
        $cloud = $seasonalMean + ($noise - 0.5) * $spread;

        return round($this->clamp01($cloud), 3);
    }

    /**
     * Cosine of the annual cycle: +1 at the coldest day, −1 half a year later.
     */
    private function seasonalCosine(GameDate $date): float
    {
        return SeasonalCycle::cosine($date->dayOfYear(), $this->calibration->coldestDayOfYear()->value);
    }

    /**
     * Deterministic pseudo-random value in [0, 1) from integer coordinates.
     */
    private function hash01(int $seed, int $index, string $salt): float
    {
        $digest = hash('sha256', $seed.':'.$index.':'.$salt);
        // 13 hex chars = 52 bits, exactly representable as a float mantissa.
        $bucket = hexdec(substr($digest, 0, 13));

        return $bucket / (16.0 ** 13);
    }

    private function lerp(float $a, float $b, float $t): float
    {
        return $a + ($b - $a) * $t;
    }

    private function smoothstep(float $t): float
    {
        return $t * $t * (3.0 - 2.0 * $t);
    }

    private function clamp01(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
