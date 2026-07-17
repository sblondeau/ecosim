<?php

declare(strict_types=1);

namespace App\Domain\Building;

use App\Domain\Energy\EnergyCalibration;

use function round;

/**
 * Converts a useful-heat need into what the heating system consumes.
 *
 * This is where the electrification lesson lives (game-design §12): for the
 * same delivered heat, the heat pump draws need/SCOP while the boiler burns
 * need/efficiency (more energy than the heat itself). The heat pump's SCOP
 * is not a fixed box spec — it depends on the emitters it feeds: old
 * high-temperature cast-iron radiators degrade it (2.5), low-temperature
 * emitters (underfloor / BT radiators) let it run near its nominal point
 * (4.3) — "a heat pump is a system, not a box". The equipment/fuel
 * characteristics are energy-conversion facts, so they come from
 * {@see EnergyCalibration} — the building side only says how much heat the
 * house needs.
 */
final readonly class HeatingEnergyCalculator
{
    public function __construct(
        private EnergyCalibration $calibration = new EnergyCalibration(),
    ) {
    }

    public function consumptionFor(HeatingSystem $system, float $needKwh, bool $lowTempEmitters = false): HeatingConsumption
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
                electricityKwh: round($needKwh / ($lowTempEmitters
                    ? $this->calibration->heatPumpScopLowTempEmitters()->value
                    : $this->calibration->heatPumpScopHighTempEmitters()->value), 2),
                fuelOilLitres: 0.0,
            ),
        };
    }
}
