<?php

declare(strict_types=1);

namespace App\Domain\Building;

/**
 * DPE energy-performance class, A (best) to G (worst).
 *
 * Phase 0-1 derives the class from a simple insulation × heating matrix — an
 * assumed simplification of the real 3CL method, good enough for the MVP's
 * property-value formula (game-design §15: « valeur du bien via une formule
 * simple liée au DPE »). Orders of magnitude: the class reflects primary
 * energy use, so insulation dominates and a heat pump improves the class
 * (SCOP ~3.5 outweighs the primary-energy factor of electricity).
 */
enum DpeClass: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case E = 'E';
    case F = 'F';
    case G = 'G';

    public static function fromBuilding(InsulationLevel $insulation, HeatingSystem $heating): self
    {
        return match ([$insulation, $heating]) {
            [InsulationLevel::None, HeatingSystem::FuelOilBoiler] => self::G,
            [InsulationLevel::None, HeatingSystem::HeatPump] => self::E,
            [InsulationLevel::Retrofitted, HeatingSystem::FuelOilBoiler] => self::E,
            [InsulationLevel::Retrofitted, HeatingSystem::HeatPump] => self::C,
            [InsulationLevel::Reinforced, HeatingSystem::FuelOilBoiler] => self::D,
            [InsulationLevel::Reinforced, HeatingSystem::HeatPump] => self::B,
        };
    }

    public function label(): string
    {
        return $this->value;
    }
}
