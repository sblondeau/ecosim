<?php

declare(strict_types=1);

namespace App\Domain\Energy;

use App\Domain\Math\SeasonalCycle;
use App\Domain\Math\SeededNoise;
use App\Domain\Time\GameDate;

use function round;

/**
 * Daily base household electricity demand (game-design §8, §11).
 *
 * Phase 0-1 scope: the base load only (appliances, lighting, hot water) — the
 * starting home is heated with fuel oil, so heating is NOT part of the electric
 * demand here; the engine adds the heat pump's electricity when one is
 * installed. Demand is slightly higher in winter (lighting, usages) and varies
 * day to day (laundry days, guests…) with seeded white noise — no two days are
 * identical, but the same seed replays identically.
 */
final readonly class EnergyDemandCalculator
{
    public function __construct(
        private EnergyCalibration $calibration = new EnergyCalibration(),
    ) {
    }

    /**
     * @return float electricity demand for the day, in kWh
     */
    public function dailyDemandKwh(int $seed, GameDate $date): float
    {
        $mean = $this->calibration->householdDailyBaseDemandKwh()->value;
        $amplitude = $this->calibration->householdDemandSeasonalAmplitudeKwh()->value;
        $peakDay = $this->calibration->householdDemandPeakDayOfYear()->value;
        $noiseBand = $this->calibration->householdDemandDailyNoiseKwh()->value;

        $demand = $mean
            + $amplitude * SeasonalCycle::at($date->dayOfYear(), $peakDay)
            + $noiseBand * SeededNoise::centered($seed, $date->dayIndex(), 'demand');

        return round($demand, 2);
    }
}
