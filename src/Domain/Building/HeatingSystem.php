<?php

declare(strict_types=1);

namespace App\Domain\Building;

/**
 * The two heating choices of the Phase 0-1 scope (game-design §15): keep the
 * old fuel-oil boiler, or switch to a heat pump.
 *
 * The choice decides which energy carrier heating consumes: fuel oil (litres,
 * outside the electric loop) or electricity (which then interacts with solar,
 * battery and grid — the electrification lesson of game-design §12).
 */
enum HeatingSystem: string
{
    case FuelOilBoiler = 'fuel_oil';
    case HeatPump = 'heat_pump';

    public function label(): string
    {
        return match ($this) {
            self::FuelOilBoiler => 'Chaudière fioul',
            self::HeatPump => 'Pompe à chaleur',
        };
    }
}
