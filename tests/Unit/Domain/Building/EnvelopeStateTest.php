<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
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
    }

    public function testWithersReturnNewImmutableState(): void
    {
        $original = new EnvelopeState(false, WallInsulation::None, Glazing::Single);

        $insulated = $original->withRoofInsulated(true)
            ->withWalls(WallInsulation::Exterior)
            ->withGlazing(Glazing::Triple);

        self::assertFalse($original->roofInsulated, 'original untouched');
        self::assertSame(WallInsulation::None, $original->walls);
        self::assertTrue($insulated->roofInsulated);
        self::assertSame(WallInsulation::Exterior, $insulated->walls);
        self::assertSame(Glazing::Triple, $insulated->glazing);
    }

    public function testLabelsAreFrench(): void
    {
        self::assertSame('Intérieure (ITI)', WallInsulation::Interior->label());
        self::assertSame('Extérieure (ITE)', WallInsulation::Exterior->label());
        self::assertSame('Double vitrage', Glazing::Double->label());
        self::assertSame('Triple vitrage', Glazing::Triple->label());
    }
}
