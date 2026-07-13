<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\HeatingNeedCalculator;
use App\Domain\Building\InsulationLevel;
use PHPUnit\Framework\TestCase;

final class HeatingNeedCalculatorTest extends TestCase
{
    public function testNoNeedAboveTheBaseTemperature(): void
    {
        $calculator = new HeatingNeedCalculator();

        self::assertSame(0.0, $calculator->dailyNeedKwh(InsulationLevel::Original, 20.0));
        self::assertSame(0.0, $calculator->dailyNeedKwh(InsulationLevel::Original, 18.0));
    }

    public function testColderDaysNeedMoreHeat(): void
    {
        $calculator = new HeatingNeedCalculator();

        $mild = $calculator->dailyNeedKwh(InsulationLevel::Original, 12.0);
        $freezing = $calculator->dailyNeedKwh(InsulationLevel::Original, 0.0);

        self::assertGreaterThan($mild, $freezing);
    }

    public function testFreezingDayInThePassoireMatchesTheDegreeDayFormula(): void
    {
        // 12.5 kWh/°C·day × 1.0 × (18 − 0) = 225 kWh — the scenario's pain.
        self::assertSame(225.0, new HeatingNeedCalculator()->dailyNeedKwh(InsulationLevel::Original, 0.0));
    }

    public function testTheSetpointMovesTheNeed(): void
    {
        $calculator = new HeatingNeedCalculator();

        // Base = setpoint − 1 gain offset. At 0 °C outside, passoire 12.5 kWh/°C·day:
        $at19 = $calculator->dailyNeedKwh(InsulationLevel::Original, 0.0, 19.0); // base 18 → 225
        $at20 = $calculator->dailyNeedKwh(InsulationLevel::Original, 0.0, 20.0); // base 19 → 237.5
        $at18 = $calculator->dailyNeedKwh(InsulationLevel::Original, 0.0, 18.0); // base 17 → 212.5

        self::assertSame(225.0, $at19, 'The default 19 °C reproduces the DJU base-18 formula.');
        self::assertSame(237.5, $at20, 'One degree warmer: one more degree-day of heat.');
        self::assertSame(212.5, $at18, 'One degree cooler saves a degree-day — the ADEME ~7 %/°C lever.');
    }

    public function testInsulationReducesTheNeed(): void
    {
        $calculator = new HeatingNeedCalculator();

        $none = $calculator->dailyNeedKwh(InsulationLevel::Original, 5.0);
        $retrofitted = $calculator->dailyNeedKwh(InsulationLevel::Retrofitted, 5.0);
        $reinforced = $calculator->dailyNeedKwh(InsulationLevel::Reinforced, 5.0);

        self::assertLessThan($none, $retrofitted);
        self::assertLessThan($retrofitted, $reinforced);
        self::assertGreaterThan(0.0, $reinforced, 'Even a well-insulated house needs some heat on a cold day.');
    }
}
