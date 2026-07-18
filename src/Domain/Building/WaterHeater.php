<?php

declare(strict_types=1);

namespace App\Domain\Building;

/**
 * How the household's domestic hot water (ECS) is produced (arbre travaux T5).
 *
 * The baseline household demand (`EnergyCalibration::householdDailyBaseDemandKwh`)
 * already includes an electric-tank water heater — resistive heating, 1 kWh
 * electricity for 1 kWh of hot water. A thermodynamic water heater is a small
 * heat pump: it moves that heat instead of generating it, so it reduces the
 * household's electricity demand by the difference (game-design §12, the same
 * "electrify with a heat pump" lesson applied to hot water instead of space
 * heating).
 */
enum WaterHeater: string
{
    case ElectricTank = 'electric_tank';
    case Thermodynamic = 'thermodynamic';

    public function label(): string
    {
        return match ($this) {
            self::ElectricTank => 'Ballon électrique',
            self::Thermodynamic => 'Chauffe-eau thermodynamique',
        };
    }
}
