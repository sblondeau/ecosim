<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * The renovation works a player can order (game-design §15 scope).
 *
 * Eligibility mirrors the real French schemes: the prime and the éco-PTZ
 * cover energy-performance renovation (insulation, heat pump) but NOT solar
 * panels or batteries — production equipment pays for itself through the
 * bills, renovation is what public money supports.
 */
enum Renovation: string
{
    /** Insulate the attic/roof (priority #1, ~24 % of losses). */
    case RoofInsulation = 'roof_insulation';
    /** Walls, interior (ITI) — cheaper, eats living space. Exclusive with ITE. */
    case WallInsulationInterior = 'wall_insulation_interior';
    /** Walls, exterior (ITE) — dearer, better. Exclusive with ITI. */
    case WallInsulationExterior = 'wall_insulation_exterior';
    /** Windows: single → double → triple glazing. */
    case Glazing = 'glazing';
    case HeatPump = 'heat_pump';
    case SolarPanels = 'solar_panels';
    case HomeBattery = 'home_battery';
    /** Fix the broken fuel-oil boiler (the breakdown-event alternative to the heat pump). */
    case BoilerRepair = 'boiler_repair';
    /** Low-temperature emitters (underfloor/oversized radiators) — boosts a heat pump's SCOP. */
    case LowTempEmitters = 'low_temp_emitters';
    /** Automatic wood-pellet boiler — replaces the generator, cheap and low-carbon fuel. */
    case PelletBoiler = 'pellet_boiler';
    /** VMC double flux — recovers heat from extracted air (arbre travaux, Tranche 5). */
    case VentilationDoubleFlow = 'ventilation_double_flow';

    /**
     * Covered by the income-based prime (MaPrimeRénov'-like)?
     */
    public function isSubsidised(): bool
    {
        return match ($this) {
            self::RoofInsulation, self::WallInsulationInterior, self::WallInsulationExterior, self::Glazing, self::HeatPump, self::LowTempEmitters, self::PelletBoiler, self::VentilationDoubleFlow => true,
            // Repairing fossil equipment is not energy-performance work.
            self::SolarPanels, self::HomeBattery, self::BoilerRepair => false,
        };
    }

    /**
     * Financeable with the zero-interest loan (éco-PTZ-like)?
     */
    public function isLoanEligible(): bool
    {
        // Same perimeter as the prime: energy-performance works only.
        return $this->isSubsidised();
    }
}
