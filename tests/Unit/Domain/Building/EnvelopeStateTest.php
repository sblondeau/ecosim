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
