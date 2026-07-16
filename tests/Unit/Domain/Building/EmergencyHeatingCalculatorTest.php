<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\EmergencyHeatingCalculator;
use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\WallInsulation;
use PHPUnit\Framework\TestCase;

final class EmergencyHeatingCalculatorTest extends TestCase
{
    private function original(): EnvelopeState
    {
        return new EnvelopeState(false, WallInsulation::None, Glazing::Single);
    }

    public function testColdDaysMaxTheHeatersOutBelowTheSurvivalSetpoint(): void
    {
        // Passoire at 3 °C: holding 16 °C would need 12.5 × 13 − 10 = 152.5 kWh
        // — far beyond the two portable heaters (96 kWh/day).
        $consumption = new EmergencyHeatingCalculator()->consumptionFor($this->original(), 3.0, 10.0);

        self::assertSame(152.5, $consumption->needKwh);
        self::assertSame(96.0, $consumption->electricityKwh, 'Capped: the heaters cannot follow.');
        self::assertSame(0.0, $consumption->fuelOilLitres, 'Joule heating burns no fuel.');
    }

    public function testMildDaysHoldTheSurvivalSetpointWithoutTheCap(): void
    {
        // 14 °C outside: 12.5 × 2 − 10 = 15 kWh suffice to hold 16 °C.
        $consumption = new EmergencyHeatingCalculator()->consumptionFor($this->original(), 14.0, 10.0);

        self::assertSame(15.0, $consumption->needKwh);
        self::assertSame(15.0, $consumption->electricityKwh);
    }

    public function testNothingRunsOnceOutdoorReachesTheSetpoint(): void
    {
        $consumption = new EmergencyHeatingCalculator()->consumptionFor($this->original(), 17.0, 10.0);

        self::assertSame(0.0, $consumption->electricityKwh);
    }

    public function testAnInsulatedHouseHoldsTheSetpointOnItsEmergencyHeaters(): void
    {
        // Best envelope at 3 °C: 12.5 × 0.50 × 13 − 10 = 71.25 kWh — UNDER the cap:
        // insulation is what makes even the emergency bearable (§8 lesson).
        $consumption = new EmergencyHeatingCalculator()->consumptionFor(
            new EnvelopeState(true, WallInsulation::Exterior, Glazing::Triple),
            3.0,
            10.0,
        );

        self::assertSame(71.25, $consumption->electricityKwh);
        self::assertLessThan(96.0, $consumption->electricityKwh);
    }
}
