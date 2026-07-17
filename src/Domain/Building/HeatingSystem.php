<?php

declare(strict_types=1);

namespace App\Domain\Building;

/**
 * The heating choices of the Phase 0-1+ scope (game-design §15, arbre travaux
 * T4): keep the old fuel-oil boiler, switch to a heat pump, or switch to a
 * pellet (granulés) boiler.
 *
 * The choice decides which energy carrier heating consumes: fuel oil (litres)
 * or pellets (kilograms), both outside the electric loop, or electricity
 * (which then interacts with solar, battery and grid — the electrification
 * lesson of game-design §12).
 */
enum HeatingSystem: string
{
    case FuelOilBoiler = 'fuel_oil';
    case HeatPump = 'heat_pump';
    case PelletBoiler = 'pellet';

    public function label(): string
    {
        return match ($this) {
            self::FuelOilBoiler => 'Chaudière fioul',
            self::HeatPump => 'Pompe à chaleur',
            self::PelletBoiler => 'Chaudière à granulés',
        };
    }
}
