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

        self::assertSame(0.0, $calculator->dailyNeedKwh(InsulationLevel::None, 20.0));
        self::assertSame(0.0, $calculator->dailyNeedKwh(InsulationLevel::None, 18.0));
    }

    public function testColderDaysNeedMoreHeat(): void
    {
        $calculator = new HeatingNeedCalculator();

        $mild = $calculator->dailyNeedKwh(InsulationLevel::None, 12.0);
        $freezing = $calculator->dailyNeedKwh(InsulationLevel::None, 0.0);

        self::assertGreaterThan($mild, $freezing);
    }

    public function testFreezingDayInThePassoireMatchesTheDegreeDayFormula(): void
    {
        // 12.5 kWh/°C·day × 1.0 × (18 − 0) = 225 kWh — the scenario's pain.
        self::assertSame(225.0, new HeatingNeedCalculator()->dailyNeedKwh(InsulationLevel::None, 0.0));
    }

    public function testInsulationReducesTheNeed(): void
    {
        $calculator = new HeatingNeedCalculator();

        $none = $calculator->dailyNeedKwh(InsulationLevel::None, 5.0);
        $retrofitted = $calculator->dailyNeedKwh(InsulationLevel::Retrofitted, 5.0);
        $reinforced = $calculator->dailyNeedKwh(InsulationLevel::Reinforced, 5.0);

        self::assertLessThan($none, $retrofitted);
        self::assertLessThan($retrofitted, $reinforced);
        self::assertGreaterThan(0.0, $reinforced, 'Even a well-insulated house needs some heat on a cold day.');
    }
}
