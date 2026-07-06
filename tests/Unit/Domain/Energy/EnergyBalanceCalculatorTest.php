<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Energy;

use App\Domain\Energy\Battery;
use App\Domain\Energy\EnergyBalanceCalculator;
use App\Domain\Energy\EnergyCalibration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnergyBalanceCalculatorTest extends TestCase
{
    private static function standardBattery(): Battery
    {
        return Battery::of(5.0, new EnergyCalibration()->batteryOneWayEfficiency());
    }

    public function testNoProductionImportsAllDemand(): void
    {
        $balance = new EnergyBalanceCalculator()->settle(0.0, 10.0, Battery::none(), 0.0);

        self::assertSame(10.0, $balance->gridImportKwh);
        self::assertSame(0.0, $balance->gridExportKwh);
        self::assertSame(0.0, $balance->selfConsumedKwh);
        self::assertSame(0.0, $balance->batteryLevelKwh);
    }

    #[DataProvider('scenarioProvider')]
    public function testSelfConsumedPlusImportAlwaysEqualsDemand(float $production, float $demand, float $level): void
    {
        $balance = new EnergyBalanceCalculator()->settle($production, $demand, self::standardBattery(), $level);

        self::assertEqualsWithDelta($demand, $balance->selfConsumedKwh + $balance->gridImportKwh, 1e-6);
    }

    /**
     * @return iterable<string, array{float, float, float}>
     */
    public static function scenarioProvider(): iterable
    {
        yield 'deficit, empty battery' => [2.0, 10.0, 0.0];
        yield 'deficit, half battery' => [2.0, 10.0, 2.5];
        yield 'surplus, empty battery' => [20.0, 10.0, 0.0];
        yield 'balanced' => [10.0, 10.0, 1.0];
        yield 'no demand' => [8.0, 0.0, 0.0];
    }

    public function testSurplusChargesBatteryBeforeExporting(): void
    {
        $balance = new EnergyBalanceCalculator()->settle(20.0, 10.0, self::standardBattery(), 0.0);

        self::assertGreaterThan(0.0, $balance->batteryChargedKwh, 'Surplus should charge the battery.');
        self::assertGreaterThan(0.0, $balance->gridExportKwh, 'Remaining surplus should be exported.');
        // The battery is filled before any export: what it stored cannot exceed its capacity.
        self::assertLessThanOrEqual(5.0, $balance->batteryChargedKwh * new EnergyCalibration()->batteryOneWayEfficiency() + 1e-9);
    }

    public function testBatteryReducesGridImportVersusNoBattery(): void
    {
        $calculator = new EnergyBalanceCalculator();

        $withBattery = $calculator->settle(8.0, 10.0, self::standardBattery(), 0.0);
        $withoutBattery = $calculator->settle(8.0, 10.0, Battery::none(), 0.0);

        self::assertLessThan($withoutBattery->gridImportKwh, $withBattery->gridImportKwh);
        self::assertGreaterThan(0.0, $withBattery->batteryDischargedKwh);
    }

    public function testRoundTripLosesEnergy(): void
    {
        $calculator = new EnergyBalanceCalculator();
        $battery = self::standardBattery();

        // Day 1: pure surplus fully charges the battery.
        $charge = $calculator->settle(20.0, 0.0, $battery, 0.0);
        // Day 2: no sun, demand drains the battery.
        $discharge = $calculator->settle(0.0, 10.0, $battery, $charge->batteryLevelKwh);

        self::assertLessThan(
            $charge->batteryChargedKwh,
            $discharge->batteryDischargedKwh,
            'Energy retrieved must be less than energy stored (round-trip loss).',
        );
    }
}
