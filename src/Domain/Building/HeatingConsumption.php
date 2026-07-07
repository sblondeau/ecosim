<?php

declare(strict_types=1);

namespace App\Domain\Building;

/**
 * What one day of heating actually consumed, by carrier.
 *
 * Exactly one of the two carriers is non-zero in Phase 0-1: the fuel-oil
 * boiler burns litres (outside the electric loop), the heat pump draws
 * electricity (which then flows through the solar/battery/grid settlement).
 */
final readonly class HeatingConsumption
{
    public function __construct(
        /** Useful heat delivered to the house, in kWh. */
        public float $needKwh,
        /** Electricity drawn by the heating system, in kWh (heat pump). */
        public float $electricityKwh,
        /** Fuel oil burnt, in litres (boiler). */
        public float $fuelOilLitres,
    ) {
    }

    public static function none(): self
    {
        return new self(0.0, 0.0, 0.0);
    }
}
