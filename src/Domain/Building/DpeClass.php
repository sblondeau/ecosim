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
 * energy use AND the 2021 double-threshold GES étiquette, so insulation
 * dominates and dropping fuel oil for a heat pump gains classes (SCOP ~3.5
 * outweighs the primary-energy factor of electricity; fioul is disastrous on
 * GES).
 *
 * Deliberately NOT in the matrix: solar panels (the real 3CL only deducts the
 * AUTOCONSUMED share — negligible while heating is fuel-oil, second-order
 * after electrification) and the battery (never counted by the real DPE).
 * See docs/backlog.md for the refinement planned with the full
 * PropertyValuation (Phase 4).
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
            [InsulationLevel::Original, HeatingSystem::FuelOilBoiler] => self::G,
            [InsulationLevel::Original, HeatingSystem::HeatPump] => self::E,
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

    /**
     * Number of classes above G (worst): G = 0, F = 1 … A = 6. Used by the
     * property-value formula (each class gained adds a sourced percentage).
     */
    public function stepsAboveWorst(): int
    {
        return match ($this) {
            self::G => 0,
            self::F => 1,
            self::E => 2,
            self::D => 3,
            self::C => 4,
            self::B => 5,
            self::A => 6,
        };
    }
}
