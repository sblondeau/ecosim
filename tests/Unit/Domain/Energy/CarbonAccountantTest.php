<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Energy;

use App\Domain\Energy\CarbonAccountant;
use PHPUnit\Framework\TestCase;

final class CarbonAccountantTest extends TestCase
{
    public function testSumsFuelCombustionAndGridImport(): void
    {
        // 100 L fuel × 9.96 kWh/L × 324 g = 322 704 g ; 1 000 kWh grid × 79 g = 79 000 g.
        $kg = new CarbonAccountant()->emittedKg(fuelOilLitres: 100.0, gridImportKwh: 1000.0);

        self::assertEqualsWithDelta(401.704, $kg, 0.001);
    }

    public function testNoActivityEmitsNothing(): void
    {
        self::assertSame(0.0, new CarbonAccountant()->emittedKg(0.0, 0.0));
    }

    public function testSelfConsumedSolarDoesNotEmit(): void
    {
        // Only the electricity actually imported counts — a fully self-sufficient
        // home (0 import) with no fuel emits nothing, however much it consumed.
        self::assertSame(0.0, new CarbonAccountant()->emittedKg(fuelOilLitres: 0.0, gridImportKwh: 0.0));

        // Fuel oil dominates the footprint: 1 L already outweighs ~40 kWh of grid.
        self::assertGreaterThan(
            new CarbonAccountant()->emittedKg(fuelOilLitres: 0.0, gridImportKwh: 40.0),
            new CarbonAccountant()->emittedKg(fuelOilLitres: 1.0, gridImportKwh: 0.0),
        );
    }

    public function testPelletsEmitLittleCo2(): void
    {
        // 100 kg × 4,6 kWh/kg = 460 kWh × 30 g = 13 800 g = 13,8 kg
        self::assertEqualsWithDelta(13.8, new CarbonAccountant()->emittedKg(0.0, 0.0, 100.0), 1e-9);
    }

    public function testPelletsEmitFarLessThanTheSameHeatFromFuelOil(): void
    {
        // Same useful heat, roughly: 100 kg pellets (4.6 kWh/kg boiler input)
        // vs. the fuel oil litres for a comparable energy content. Pellets
        // stay dramatically lower on CO₂ (30 g/kWh vs 324 g/kWh).
        self::assertLessThan(
            new CarbonAccountant()->emittedKg(fuelOilLitres: 46.0, gridImportKwh: 0.0),
            new CarbonAccountant()->emittedKg(fuelOilLitres: 0.0, gridImportKwh: 0.0, pelletKg: 100.0),
        );
    }
}
