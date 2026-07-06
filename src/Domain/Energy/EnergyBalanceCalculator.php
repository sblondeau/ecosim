<?php

declare(strict_types=1);

namespace App\Domain\Energy;

use function max;
use function min;
use function round;

/**
 * Settles one day of energy flows between solar production, household demand,
 * the battery and the grid (game-design §8, §18).
 *
 * The day is split into a daytime phase (solar produces) and a night/evening
 * phase (no solar, but the demand peak). Priority of use:
 *   1. solar covers daytime demand directly (self-consumption is worth most);
 *   2. daytime surplus charges the battery, then any remainder is exported;
 *   3. night demand is covered by the battery, then imported from the grid.
 *
 * This day/night split is what gives the battery value — it bridges the midday
 * production surplus to the evening demand peak (§18). Pure and deterministic.
 */
final readonly class EnergyBalanceCalculator
{
    public function __construct(
        private EnergyCalibration $calibration = new EnergyCalibration(),
    ) {
    }

    /**
     * @param float $productionKwh solar produced during the day
     * @param float $demandKwh     total household demand for the day
     * @param float $batteryLevel  battery charge at the start of the day, in kWh
     */
    public function settle(float $productionKwh, float $demandKwh, Battery $battery, float $batteryLevel): EnergyBalance
    {
        $daytimeFraction = $this->calibration->daytimeDemandFraction()->value;
        $daytimeDemand = $demandKwh * $daytimeFraction;
        $nightDemand = $demandKwh - $daytimeDemand;

        $level = $this->clampLevel($batteryLevel, $battery);
        $selfConsumed = 0.0;
        $gridImport = 0.0;
        $gridExport = 0.0;
        $charged = 0.0;
        $discharged = 0.0;

        // --- Daytime: solar meets daytime demand directly ---
        $directUse = min($productionKwh, $daytimeDemand);
        $selfConsumed += $directUse;
        $surplus = $productionKwh - $directUse;
        $daytimeDeficit = $daytimeDemand - $directUse;

        if ($daytimeDeficit > 0.0) {
            // Production too low even for daytime: draw from battery, then grid.
            $fromBattery = $this->deliverableFrom($level, $battery, $daytimeDeficit);
            $discharged += $fromBattery;
            $selfConsumed += $fromBattery;
            $level -= $fromBattery / $battery->dischargeEfficiency;
            $gridImport += $daytimeDeficit - $fromBattery;
        } else {
            // Daytime surplus: store into the battery, export the rest.
            $stored = $this->storableInputInto($level, $battery, $surplus);
            $charged += $stored;
            $level += $stored * $battery->chargeEfficiency;
            $gridExport += $surplus - $stored;
        }

        // --- Night/evening: battery first, then grid ---
        $fromBatteryNight = $this->deliverableFrom($level, $battery, $nightDemand);
        $discharged += $fromBatteryNight;
        $selfConsumed += $fromBatteryNight;
        $level -= $fromBatteryNight / $battery->dischargeEfficiency;
        $gridImport += $nightDemand - $fromBatteryNight;

        return new EnergyBalance(
            productionKwh: round($productionKwh, 3),
            demandKwh: round($demandKwh, 3),
            selfConsumedKwh: round($selfConsumed, 3),
            gridImportKwh: round($gridImport, 3),
            gridExportKwh: round($gridExport, 3),
            batteryChargedKwh: round($charged, 3),
            batteryDischargedKwh: round($discharged, 3),
            batteryLevelKwh: round($this->clampLevel($level, $battery), 3),
        );
    }

    /**
     * Energy the battery can deliver to the load, capped by the request.
     */
    private function deliverableFrom(float $level, Battery $battery, float $requested): float
    {
        if ($requested <= 0.0) {
            return 0.0;
        }

        $deliverable = $level * $battery->dischargeEfficiency;

        return min($requested, $deliverable);
    }

    /**
     * Amount of the offered surplus that gets consumed to charge the battery
     * (input side), capped by the remaining headroom.
     */
    private function storableInputInto(float $level, Battery $battery, float $surplus): float
    {
        if ($surplus <= 0.0) {
            return 0.0;
        }

        $headroom = $battery->capacityKwh - $level;
        if ($headroom <= 0.0) {
            return 0.0;
        }

        $maxInput = $headroom / $battery->chargeEfficiency;

        return min($surplus, $maxInput);
    }

    private function clampLevel(float $level, Battery $battery): float
    {
        return max(0.0, min($battery->capacityKwh, $level));
    }
}
