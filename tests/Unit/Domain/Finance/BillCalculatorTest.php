<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Building\HeatingConsumption;
use App\Domain\Energy\EnergyBalance;
use App\Domain\Finance\BillCalculator;
use App\Domain\Finance\FinanceCalibration;
use PHPUnit\Framework\TestCase;

final class BillCalculatorTest extends TestCase
{
    private static function balance(float $import, float $export): EnergyBalance
    {
        return new EnergyBalance(0.0, $import, 0.0, $import, $export, 0.0, 0.0, 0.0);
    }

    public function testPricesEachLineSeparately(): void
    {
        $bill = new BillCalculator()->billFor(
            self::balance(import: 10.0, export: 0.0),
            new HeatingConsumption(needKwh: 100.0, electricityKwh: 0.0, fuelOilLitres: 10.0),
        );

        // 10 kWh × 0,22 € et 10 L × 1,20 €.
        self::assertSame(220, $bill->electricityCost->cents);
        self::assertSame(1200, $bill->fuelOilCost->cents);
        self::assertSame(0, $bill->surplusRevenue->cents);
        self::assertSame(1420, $bill->netCost()->cents);
    }

    public function testSurplusRevenueIsACredit(): void
    {
        $bill = new BillCalculator()->billFor(
            self::balance(import: 0.0, export: 10.0),
            HeatingConsumption::none(),
        );

        // 10 kWh exportés × 0,011 € — presque rien : la leçon du §8.
        self::assertSame(11, $bill->surplusRevenue->cents);
        self::assertSame(-11, $bill->netCost()->cents, 'A pure-surplus day nets a (tiny) credit.');
    }

    public function testSelfConsumptionIsWorthFarMoreThanResale(): void
    {
        $calibration = new FinanceCalibration();

        $ratio = $calibration->electricityPricePerKwh()->value
            / $calibration->surplusSellPricePerKwh()->value;

        // Game-design §8 : l'autoconsommation vaut ~18-20× la revente.
        self::assertGreaterThan(15.0, $ratio);
    }
}
