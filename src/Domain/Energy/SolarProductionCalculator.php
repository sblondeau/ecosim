<?php

declare(strict_types=1);

namespace App\Domain\Energy;

use App\Domain\Math\SeasonalCycle;
use App\Domain\Time\GameDate;
use App\Domain\Weather\Weather;

/**
 * Daily solar production of a rooftop PV installation (game-design §3, §15).
 *
 * Pure and deterministic:
 *   output = peakPowerKwc × clearSkyPeakSunHours(season) × cloudFactor(weather) × performanceRatio
 *
 * Clear-sky peak-sun-hours follow the annual cycle (max at the summer solstice);
 * clouds reduce output but never to zero (diffuse light). Coefficients come from
 * {@see EnergyCalibration}.
 */
final readonly class SolarProductionCalculator
{
    public function __construct(
        private EnergyCalibration $calibration = new EnergyCalibration(),
    ) {
    }

    /**
     * @param float $peakPowerKwc installed peak power (0 = no panels)
     *
     * @return float produced energy for the day, in kWh
     */
    public function dailyProductionKwh(float $peakPowerKwc, Weather $weather, GameDate $date): float
    {
        if ($peakPowerKwc <= 0.0) {
            return 0.0;
        }

        $mean = $this->calibration->solarClearSkyPeakSunHoursMean()->value;
        $amplitude = $this->calibration->solarSeasonalAmplitudeHours()->value;
        $peakDay = $this->calibration->solarPeakDayOfYear()->value;

        $clearSkyHours = $mean + $amplitude * SeasonalCycle::cosine($date->dayOfYear(), $peakDay);

        $cloudLoss = $this->calibration->solarCloudLossFactor()->value;
        $cloudFactor = 1.0 - $cloudLoss * $weather->cloudCover;

        $performanceRatio = $this->calibration->solarPerformanceRatio()->value;

        return round($peakPowerKwc * $clearSkyHours * $cloudFactor * $performanceRatio, 2);
    }
}
