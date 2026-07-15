<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\DpeCertifier;
use App\Domain\Building\DpeClass;
use PHPUnit\Framework\TestCase;

final class DpeCertifierTest extends TestCase
{
    public function testFuelOilPassoireIsGDrivenByEmissions(): void
    {
        // ~100 m² passoire: base electricity + a full year of fuel-oil heating.
        $dpe = new DpeCertifier()->certify(electricityKwh: 3650.0, fuelOilLitres: 3150.0);

        self::assertSame(DpeClass::F, $dpe->energyClass);
        self::assertSame(DpeClass::G, $dpe->climateClass, 'Fuel oil is disastrous on CO₂.');
        self::assertSame(DpeClass::G, $dpe->finalClass, 'Final class = the worse of the two.');
    }

    public function testHeatPumpCollapsesTheClimateLabelBeforeTheEnergyLabel(): void
    {
        // Same leaky envelope, heat pump instead of the boiler: lots of electricity.
        $dpe = new DpeCertifier()->certify(electricityKwh: 11266.0, fuelOilLitres: 0.0);

        self::assertSame(DpeClass::E, $dpe->energyClass, 'Primary energy still mediocre (×2.3 factor).');
        self::assertSame(DpeClass::B, $dpe->climateClass, 'But emissions crater — low-carbon electricity.');
        self::assertSame(DpeClass::E, $dpe->finalClass);
    }

    public function testFullyRenovatedReachesCWithClimateA(): void
    {
        $dpe = new DpeCertifier()->certify(electricityKwh: 5932.0, fuelOilLitres: 0.0);

        self::assertSame(DpeClass::C, $dpe->energyClass);
        self::assertSame(DpeClass::A, $dpe->climateClass);
        self::assertSame(DpeClass::C, $dpe->finalClass);
    }

    public function testCursorStaysWithinTheBand(): void
    {
        $dpe = new DpeCertifier()->certify(3650.0, 3150.0);

        self::assertGreaterThanOrEqual(0.0, $dpe->energyBandFillPct);
        self::assertLessThanOrEqual(100.0, $dpe->energyBandFillPct);
        self::assertGreaterThanOrEqual(0.0, $dpe->climateBandFillPct);
        self::assertLessThanOrEqual(100.0, $dpe->climateBandFillPct);
    }
}
