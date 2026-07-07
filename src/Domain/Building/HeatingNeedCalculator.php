<?php

declare(strict_types=1);

namespace App\Domain\Building;

use function max;
use function round;

/**
 * Daily heating need of the house, by the standard degree-day method:
 *
 *   need = heatLoss × insulationFactor × max(0, baseTemperature − outdoor)
 *
 * The need is the useful heat the house must receive to hold the setpoint —
 * what it costs to deliver it depends on the heating system
 * ({@see HeatingEnergyCalculator}). Pure and deterministic.
 */
final readonly class HeatingNeedCalculator
{
    public function __construct(
        private BuildingCalibration $calibration = new BuildingCalibration(),
    ) {
    }

    /**
     * @param float $outdoorC daily-mean outdoor temperature, in °C
     *
     * @return float useful heat needed for the day, in kWh (0 outside the heating season)
     */
    public function dailyNeedKwh(InsulationLevel $insulation, float $outdoorC): float
    {
        $degreeDays = max(0.0, $this->calibration->heatingBaseTemperatureC()->value - $outdoorC);

        $need = $this->calibration->heatLossKwhPerDegreeDay()->value
            * $this->calibration->insulationFactor($insulation)->value
            * $degreeDays;

        return round($need, 2);
    }
}
