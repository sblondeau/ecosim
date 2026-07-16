<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingNeedCalculator;
use App\Domain\Building\WallInsulation;
use PHPUnit\Framework\TestCase;

final class HeatingNeedCalculatorTest extends TestCase
{
    private function original(): EnvelopeState
    {
        return new EnvelopeState(false, WallInsulation::None, Glazing::Single);
    }

    public function testNoNeedAboveTheBaseTemperature(): void
    {
        $calculator = new HeatingNeedCalculator();

        self::assertSame(0.0, $calculator->dailyNeedKwh($this->original(), 20.0));
        self::assertSame(0.0, $calculator->dailyNeedKwh($this->original(), 18.0));
    }

    public function testColderDaysNeedMoreHeat(): void
    {
        $calculator = new HeatingNeedCalculator();

        $mild = $calculator->dailyNeedKwh($this->original(), 12.0);
        $freezing = $calculator->dailyNeedKwh($this->original(), 0.0);

        self::assertGreaterThan($mild, $freezing);
    }

    public function testOriginalHouseNeedsFullHeat(): void
    {
        // 12.5 kWh/°C·day × 1.0 × (18 − 0) = 225 kWh — the scenario's pain.
        $need = new HeatingNeedCalculator()->dailyNeedKwh($this->original(), 0.0);
        self::assertSame(225.0, $need);
    }

    public function testInsulatedEnvelopeNeedsLess(): void
    {
        $need = new HeatingNeedCalculator()->dailyNeedKwh(
            new EnvelopeState(true, WallInsulation::Interior, Glazing::Double),
            0.0,
        );
        self::assertSame(120.38, $need); // 12,5 × 0,535 × 18 = 120,375 → round 120,38
    }

    public function testTheSetpointMovesTheNeed(): void
    {
        $calculator = new HeatingNeedCalculator();

        // Base = setpoint − 1 gain offset. At 0 °C outside, passoire 12.5 kWh/°C·day:
        $at19 = $calculator->dailyNeedKwh($this->original(), 0.0, 19.0); // base 18 → 225
        $at20 = $calculator->dailyNeedKwh($this->original(), 0.0, 20.0); // base 19 → 237.5
        $at18 = $calculator->dailyNeedKwh($this->original(), 0.0, 18.0); // base 17 → 212.5

        self::assertSame(225.0, $at19, 'The default 19 °C reproduces the DJU base-18 formula.');
        self::assertSame(237.5, $at20, 'One degree warmer: one more degree-day of heat.');
        self::assertSame(212.5, $at18, 'One degree cooler saves a degree-day — the ADEME ~7 %/°C lever.');
    }

    public function testEnvelopeSurfacesReduceTheNeed(): void
    {
        $calculator = new HeatingNeedCalculator();

        $none = $calculator->dailyNeedKwh($this->original(), 5.0);
        $midEnvelope = $calculator->dailyNeedKwh(new EnvelopeState(true, WallInsulation::Interior, Glazing::Double), 5.0);
        $bestEnvelope = $calculator->dailyNeedKwh(new EnvelopeState(true, WallInsulation::Exterior, Glazing::Triple), 5.0);

        self::assertLessThan($none, $midEnvelope);
        self::assertLessThan($midEnvelope, $bestEnvelope);
        self::assertGreaterThan(0.0, $bestEnvelope, 'Even a well-insulated house needs some heat on a cold day.');
    }
}
