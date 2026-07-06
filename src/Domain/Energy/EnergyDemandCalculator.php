<?php

declare(strict_types=1);

namespace App\Domain\Energy;

use App\Domain\Math\SeasonalCycle;
use App\Domain\Time\GameDate;

/**
 * Daily base household electricity demand (game-design §8, §11).
 *
 * Phase 0-1 scope: the base load only (appliances, lighting, hot water) — the
 * starting home is heated with fuel oil, so heating is NOT part of the electric
 * demand yet. It becomes electric only once a heat pump is installed (later
 * step). Demand is slightly higher in winter (lighting, usages).
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
    public function dailyDemandKwh(GameDate $date): float
    {
        $mean = $this->calibration->householdDailyBaseDemandKwh()->value;
        $amplitude = $this->calibration->householdDemandSeasonalAmplitudeKwh()->value;
        $peakDay = $this->calibration->householdDemandPeakDayOfYear()->value;

        $demand = $mean + $amplitude * SeasonalCycle::at($date->dayOfYear(), $peakDay);

        return round($demand, 2);
    }
}
