<?php

declare(strict_types=1);

namespace App\Domain\Building;

use function round;

/**
 * Converts a useful-heat need into what the heating system consumes.
 *
 * This is where the electrification lesson lives (game-design §12): for the
 * same delivered heat, the heat pump draws need/SCOP (~3.5× less final energy)
 * while the boiler burns need/efficiency (more energy than the heat itself).
 */
final readonly class HeatingEnergyCalculator
{
    public function __construct(
        private BuildingCalibration $calibration = new BuildingCalibration(),
    ) {
    }

    public function consumptionFor(HeatingSystem $system, float $needKwh): HeatingConsumption
    {
        if ($needKwh <= 0.0) {
            return HeatingConsumption::none();
        }

        return match ($system) {
            HeatingSystem::FuelOilBoiler => new HeatingConsumption(
                needKwh: $needKwh,
                electricityKwh: 0.0,
                fuelOilLitres: round(
                    $needKwh
                        / $this->calibration->fuelOilBoilerEfficiency()->value
                        / $this->calibration->fuelOilEnergyKwhPerLitre()->value,
                    2,
                ),
            ),
            HeatingSystem::HeatPump => new HeatingConsumption(
                needKwh: $needKwh,
                electricityKwh: round($needKwh / $this->calibration->heatPumpScop()->value, 2),
                fuelOilLitres: 0.0,
            ),
        };
    }
}
