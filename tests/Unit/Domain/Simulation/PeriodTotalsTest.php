<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Simulation;

use App\Domain\Energy\EnergyBalance;
use App\Domain\Simulation\PeriodTotals;
use PHPUnit\Framework\TestCase;

final class PeriodTotalsTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $totals = new PeriodTotals();

        self::assertSame(0.0, $totals->productionKwh);
        self::assertSame(0, $totals->days);
        self::assertSame(1.0, $totals->selfSufficiencyRatio(), 'No demand yet means fully self-sufficient.');
        self::assertSame(100, $totals->averageComfortScore(), 'No day lived yet means full comfort.');
    }

    public function testAddAccumulatesEachFlow(): void
    {
        $balance = new EnergyBalance(
            productionKwh: 12.0,
            demandKwh: 10.0,
            selfConsumedKwh: 7.0,
            gridImportKwh: 3.0,
            gridExportKwh: 5.0,
            batteryChargedKwh: 2.0,
            batteryDischargedKwh: 1.0,
            batteryLevelKwh: 1.0,
        );

        $totals = new PeriodTotals()->add($balance, 8.5, 70)->add($balance, 1.5, 90);

        self::assertSame(24.0, $totals->productionKwh);
        self::assertSame(20.0, $totals->demandKwh);
        self::assertSame(6.0, $totals->importKwh);
        self::assertSame(10.0, $totals->exportKwh);
        self::assertSame(10.0, $totals->fuelOilLitres);
        self::assertSame(2, $totals->days);
        self::assertSame(80, $totals->averageComfortScore());
    }

    public function testSelfSufficiencyRatioReflectsImports(): void
    {
        $balance = new EnergyBalance(10.0, 10.0, 6.0, 4.0, 0.0, 0.0, 0.0, 0.0);

        // 4 kWh imported out of 10 kWh demand -> 60% covered by own production.
        self::assertEqualsWithDelta(0.6, new PeriodTotals()->add($balance, 0.0, 100)->selfSufficiencyRatio(), 1e-9);
    }
}
