<?php

declare(strict_types=1);

namespace App\Domain\Building;

/**
 * What one day of heating actually consumed, by carrier.
 *
 * Exactly one of the carriers is non-zero (arbre travaux T4 adds a third):
 * the fuel-oil boiler burns litres, the pellet boiler burns kilograms — both
 * outside the electric loop — the heat pump draws electricity (which then
 * flows through the solar/battery/grid settlement).
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
        /** Wood pellets burnt, in kilograms (pellet boiler). */
        public float $pelletKg = 0.0,
    ) {
    }

    public static function none(): self
    {
        return new self(0.0, 0.0, 0.0);
    }
}
