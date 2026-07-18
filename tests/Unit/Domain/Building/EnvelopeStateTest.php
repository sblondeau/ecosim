<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\Ventilation;
use App\Domain\Building\WallInsulation;
use PHPUnit\Framework\TestCase;

final class EnvelopeStateTest extends TestCase
{
    public function testOriginalHouseIsUninsulatedSingleGlazed(): void
    {
        $envelope = new EnvelopeState(false, WallInsulation::None, Glazing::Single);

        self::assertFalse($envelope->roofInsulated);
        self::assertSame(WallInsulation::None, $envelope->walls);
        self::assertSame(Glazing::Single, $envelope->glazing);
        self::assertSame(Ventilation::None, $envelope->ventilation, 'Ventilation defaults to None for existing 3-arg constructions.');
    }

    public function testWithersReturnNewImmutableState(): void
    {
        $original = new EnvelopeState(false, WallInsulation::None, Glazing::Single);

        $insulated = $original->withRoofInsulated(true)
            ->withWalls(WallInsulation::Exterior)
            ->withGlazing(Glazing::Triple)
            ->withVentilation(Ventilation::DoubleFlow);

        self::assertFalse($original->roofInsulated, 'original untouched');
        self::assertSame(WallInsulation::None, $original->walls);
        self::assertSame(Ventilation::None, $original->ventilation);
        self::assertTrue($insulated->roofInsulated);
        self::assertSame(WallInsulation::Exterior, $insulated->walls);
        self::assertSame(Glazing::Triple, $insulated->glazing);
        self::assertSame(Ventilation::DoubleFlow, $insulated->ventilation);
    }

    public function testRenovatingOneSurfacePreservesTheOthersIncludingVentilation(): void
    {
        $envelope = new EnvelopeState(false, WallInsulation::Interior, Glazing::Double, Ventilation::DoubleFlow);

        $roofRenovated = $envelope->withRoofInsulated(true);
        self::assertSame(WallInsulation::Interior, $roofRenovated->walls, 'withRoofInsulated must not reset walls');
        self::assertSame(Glazing::Double, $roofRenovated->glazing, 'withRoofInsulated must not reset glazing');
        self::assertSame(Ventilation::DoubleFlow, $roofRenovated->ventilation, 'withRoofInsulated must not reset ventilation');

        $wallsRenovated = $envelope->withWalls(WallInsulation::Exterior);
        self::assertSame(Glazing::Double, $wallsRenovated->glazing, 'withWalls must not reset glazing');
        self::assertSame(Ventilation::DoubleFlow, $wallsRenovated->ventilation, 'withWalls must not reset ventilation');

        $glazingRenovated = $envelope->withGlazing(Glazing::Triple);
        self::assertSame(WallInsulation::Interior, $glazingRenovated->walls, 'withGlazing must not reset walls');
        self::assertSame(Ventilation::DoubleFlow, $glazingRenovated->ventilation, 'withGlazing must not reset ventilation');

        $ventilationRenovated = $envelope->withVentilation(Ventilation::None);
        self::assertSame(WallInsulation::Interior, $ventilationRenovated->walls, 'withVentilation must not reset walls');
        self::assertSame(Glazing::Double, $ventilationRenovated->glazing, 'withVentilation must not reset glazing');
    }

    /**
     * The wither trap: with 6 fields, EVERY with* method must thread ALL SIX
     * into `new self(...)`. A missed thread silently resets a gesture (or any
     * other field) on an unrelated renovation. Starts from a fully
     * non-default envelope — every field set to something other than its
     * default — so a dropped thread cannot hide behind a coincidental default.
     */
    public function testEveryWitherPreservesAllOtherFieldsFromAFullyNonDefaultEnvelope(): void
    {
        $envelope = new EnvelopeState(
            roofInsulated: true,
            walls: WallInsulation::Exterior,
            glazing: Glazing::Triple,
            ventilation: Ventilation::DoubleFlow,
            draughtProofed: true,
            thermalCurtains: true,
        );

        $afterRoof = $envelope->withRoofInsulated(false);
        self::assertSame(WallInsulation::Exterior, $afterRoof->walls, 'withRoofInsulated must not reset walls');
        self::assertSame(Glazing::Triple, $afterRoof->glazing, 'withRoofInsulated must not reset glazing');
        self::assertSame(Ventilation::DoubleFlow, $afterRoof->ventilation, 'withRoofInsulated must not reset ventilation');
        self::assertTrue($afterRoof->draughtProofed, 'withRoofInsulated must not reset draughtProofed');
        self::assertTrue($afterRoof->thermalCurtains, 'withRoofInsulated must not reset thermalCurtains');

        $afterWalls = $envelope->withWalls(WallInsulation::None);
        self::assertTrue($afterWalls->roofInsulated, 'withWalls must not reset roofInsulated');
        self::assertSame(Glazing::Triple, $afterWalls->glazing, 'withWalls must not reset glazing');
        self::assertSame(Ventilation::DoubleFlow, $afterWalls->ventilation, 'withWalls must not reset ventilation');
        self::assertTrue($afterWalls->draughtProofed, 'withWalls must not reset draughtProofed');
        self::assertTrue($afterWalls->thermalCurtains, 'withWalls must not reset thermalCurtains');

        $afterGlazing = $envelope->withGlazing(Glazing::Single);
        self::assertTrue($afterGlazing->roofInsulated, 'withGlazing must not reset roofInsulated');
        self::assertSame(WallInsulation::Exterior, $afterGlazing->walls, 'withGlazing must not reset walls');
        self::assertSame(Ventilation::DoubleFlow, $afterGlazing->ventilation, 'withGlazing must not reset ventilation');
        self::assertTrue($afterGlazing->draughtProofed, 'withGlazing must not reset draughtProofed');
        self::assertTrue($afterGlazing->thermalCurtains, 'withGlazing must not reset thermalCurtains');

        $afterVentilation = $envelope->withVentilation(Ventilation::None);
        self::assertTrue($afterVentilation->roofInsulated, 'withVentilation must not reset roofInsulated');
        self::assertSame(WallInsulation::Exterior, $afterVentilation->walls, 'withVentilation must not reset walls');
        self::assertSame(Glazing::Triple, $afterVentilation->glazing, 'withVentilation must not reset glazing');
        self::assertTrue($afterVentilation->draughtProofed, 'withVentilation must not reset draughtProofed');
        self::assertTrue($afterVentilation->thermalCurtains, 'withVentilation must not reset thermalCurtains');

        $afterDraughtProofed = $envelope->withDraughtProofed(false);
        self::assertTrue($afterDraughtProofed->roofInsulated, 'withDraughtProofed must not reset roofInsulated');
        self::assertSame(WallInsulation::Exterior, $afterDraughtProofed->walls, 'withDraughtProofed must not reset walls');
        self::assertSame(Glazing::Triple, $afterDraughtProofed->glazing, 'withDraughtProofed must not reset glazing');
        self::assertSame(Ventilation::DoubleFlow, $afterDraughtProofed->ventilation, 'withDraughtProofed must not reset ventilation');
        self::assertTrue($afterDraughtProofed->thermalCurtains, 'withDraughtProofed must not reset thermalCurtains');

        $afterThermalCurtains = $envelope->withThermalCurtains(false);
        self::assertTrue($afterThermalCurtains->roofInsulated, 'withThermalCurtains must not reset roofInsulated');
        self::assertSame(WallInsulation::Exterior, $afterThermalCurtains->walls, 'withThermalCurtains must not reset walls');
        self::assertSame(Glazing::Triple, $afterThermalCurtains->glazing, 'withThermalCurtains must not reset glazing');
        self::assertSame(Ventilation::DoubleFlow, $afterThermalCurtains->ventilation, 'withThermalCurtains must not reset ventilation');
        self::assertTrue($afterThermalCurtains->draughtProofed, 'withThermalCurtains must not reset draughtProofed');
    }

    public function testLabelsAreFrench(): void
    {
        self::assertSame('Intérieure (ITI)', WallInsulation::Interior->label());
        self::assertSame('Extérieure (ITE)', WallInsulation::Exterior->label());
        self::assertSame('Double vitrage', Glazing::Double->label());
        self::assertSame('Triple vitrage', Glazing::Triple->label());
        self::assertSame('Aucune (naturelle)', Ventilation::None->label());
        self::assertSame('VMC double flux', Ventilation::DoubleFlow->label());
    }
}
