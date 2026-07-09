<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Simulation;

use App\Domain\Building\HeatingConsumption;
use App\Domain\Building\ThermalComfort;
use App\Domain\Energy\EnergyBalance;
use App\Domain\Finance\DailyBill;
use App\Domain\Finance\Money;
use App\Domain\Simulation\DailySnapshot;
use App\Domain\Simulation\PeriodTotals;
use App\Domain\Time\GameDate;
use App\Domain\Weather\Weather;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PeriodTotalsTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $totals = new PeriodTotals();

        self::assertSame(0.0, $totals->productionKwh);
        self::assertSame(0, $totals->days);
        self::assertSame(0, $totals->netEnergyCost()->cents);
        self::assertSame(1.0, $totals->selfSufficiencyRatio(), 'No demand yet means fully self-sufficient.');
        self::assertSame(100, $totals->averageComfortScore(), 'No day lived yet means full comfort.');
    }

    public function testAddAccumulatesEachFlow(): void
    {
        $day = self::day();

        $totals = new PeriodTotals()->add($day)->add($day);

        self::assertSame(24.0, $totals->productionKwh);
        self::assertSame(20.0, $totals->demandKwh);
        self::assertSame(6.0, $totals->importKwh);
        self::assertSame(10.0, $totals->exportKwh);
        self::assertSame(17.0, $totals->fuelOilLitres);
        self::assertSame(2, $totals->days);
        self::assertSame(80, $totals->averageComfortScore());
        self::assertSame(2 * 66, $totals->electricityCost->cents);
        self::assertSame(2 * 1020, $totals->fuelOilCost->cents);
        self::assertSame(2 * 6, $totals->surplusRevenue->cents);
        self::assertSame(2 * (66 + 1020 - 6), $totals->netEnergyCost()->cents);
    }

    public function testSelfSufficiencyRatioReflectsImports(): void
    {
        // 3 kWh imported out of 10 kWh demand -> 70% covered by own production.
        self::assertEqualsWithDelta(0.7, new PeriodTotals()->add(self::day())->selfSufficiencyRatio(), 1e-9);
    }

    private static function day(): DailySnapshot
    {
        $epoch = DateTimeImmutable::createFromFormat('!Y-m-d', '2025-01-01');
        self::assertInstanceOf(DateTimeImmutable::class, $epoch);

        return new DailySnapshot(
            date: GameDate::epoch($epoch),
            weather: new Weather(0.5, 5.0),
            balance: new EnergyBalance(
                productionKwh: 12.0,
                demandKwh: 10.0,
                selfConsumedKwh: 7.0,
                gridImportKwh: 3.0,
                gridExportKwh: 5.0,
                batteryChargedKwh: 2.0,
                batteryDischargedKwh: 1.0,
                batteryLevelKwh: 1.0,
            ),
            heating: new HeatingConsumption(needKwh: 72.0, electricityKwh: 0.0, fuelOilLitres: 8.5),
            comfort: new ThermalComfort(indoorC: 19.0, feltC: 17.0, score: 80),
            bill: new DailyBill(
                electricityCost: Money::fromCents(66),
                fuelOilCost: Money::fromCents(1020),
                surplusRevenue: Money::fromCents(6),
            ),
            incomeCredited: Money::zero(),
            loanPayment: Money::zero(),
        );
    }
}
