<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\InsulationLevel;
use App\Domain\Building\ThermalComfortCalculator;
use PHPUnit\Framework\TestCase;

final class ThermalComfortCalculatorTest extends TestCase
{
    public function testHeatingHoldsTheSetpointDuringTheHeatingSeason(): void
    {
        $comfort = new ThermalComfortCalculator()->comfortFor(InsulationLevel::Original, 0.0);

        self::assertSame(19.0, $comfort->indoorC);
    }

    public function testTheHouseFreeRunsOutsideTheHeatingSeason(): void
    {
        $comfort = new ThermalComfortCalculator()->comfortFor(InsulationLevel::Original, 22.0);

        self::assertSame(22.0, $comfort->indoorC);
        self::assertSame(22.0, $comfort->feltC, 'No indoor/outdoor gap, no cold-wall effect.');
        self::assertSame(100, $comfort->score);
    }

    public function testColdWallsMakeThePassoireFeelColderThanItsAir(): void
    {
        // Freezing day, uninsulated: 19 °C of air, felt 19 − 0.15 × 19 ≈ 16.2 °C.
        $comfort = new ThermalComfortCalculator()->comfortFor(InsulationLevel::Original, 0.0);

        self::assertSame(16.2, $comfort->feltC);
        self::assertSame(72, $comfort->score, '2.8 °C below range × 10 pts = 72.');
    }

    public function testInsulationImprovesComfortAtTheSameSetpoint(): void
    {
        $calculator = new ThermalComfortCalculator();

        $passoire = $calculator->comfortFor(InsulationLevel::Original, 0.0);
        $reinforced = $calculator->comfortFor(InsulationLevel::Reinforced, 0.0);

        self::assertGreaterThan($passoire->feltC, $reinforced->feltC);
        self::assertGreaterThan($passoire->score, $reinforced->score);
        // The pedagogical claim (game-design §8): insulation buys comfort, not just smaller bills.
        self::assertGreaterThanOrEqual(94, $reinforced->score);
    }

    public function testAnUnheatedPassoireSettlesJustAboveTheOutdoorTemperature(): void
    {
        // Steady state: 10 kWh of internal gains / 12.5 kWh/°C·day of losses = +0.8 °C.
        $comfort = new ThermalComfortCalculator()->unheatedComfortFor(InsulationLevel::Original, 3.0, 10.0);

        self::assertSame(3.8, $comfort->indoorC);
        self::assertSame(0, $comfort->score, 'Far below the comfort range: the breakdown must hurt.');
    }

    public function testInsulationHoldsMoreOfTheInternalGainsWhenUnheated(): void
    {
        $calculator = new ThermalComfortCalculator();

        $passoire = $calculator->unheatedComfortFor(InsulationLevel::Original, 3.0, 10.0);
        // 10 kWh / (12.5 × 0.30) = +2.7 °C: same gains, three times the temperature rise.
        $reinforced = $calculator->unheatedComfortFor(InsulationLevel::Reinforced, 3.0, 10.0);

        self::assertGreaterThan($passoire->indoorC, $reinforced->indoorC);
        self::assertSame(5.7, $reinforced->indoorC);
    }

    public function testUnheatedIndoorNeverExceedsTheSetpoint(): void
    {
        // A mild shoulder-season day: huge gains cannot warm the house past the setpoint.
        $comfort = new ThermalComfortCalculator()->unheatedComfortFor(InsulationLevel::Reinforced, 17.0, 50.0);

        self::assertSame(19.0, $comfort->indoorC);
    }

    public function testUnheatedFreeRunsLikeHeatedOutsideTheHeatingSeason(): void
    {
        $unheated = new ThermalComfortCalculator()->unheatedComfortFor(InsulationLevel::Original, 22.0, 10.0);

        self::assertSame(22.0, $unheated->indoorC, 'No heating needed: the breakdown changes nothing in summer.');
        self::assertSame(100, $unheated->score);
    }

    public function testALowerSetpointHoldsTheHouseColder(): void
    {
        $calculator = new ThermalComfortCalculator();

        $at21 = $calculator->comfortFor(InsulationLevel::Original, 0.0, 21.0);
        $at19 = $calculator->comfortFor(InsulationLevel::Original, 0.0, 19.0);
        $at16 = $calculator->comfortFor(InsulationLevel::Original, 0.0, 16.0);

        self::assertSame(21.0, $at21->indoorC);
        self::assertSame(19.0, $at19->indoorC);
        self::assertSame(16.0, $at16->indoorC, 'Dialling down the thermostat directly lowers the indoor air.');
        self::assertGreaterThan($at16->score, $at19->score, 'Under-heating to save money costs comfort.');
    }

    public function testScoreDegradesProgressivelyNeverACliff(): void
    {
        $calculator = new ThermalComfortCalculator();

        $mild = $calculator->comfortFor(InsulationLevel::Original, 10.0);
        $freezing = $calculator->comfortFor(InsulationLevel::Original, -5.0);

        self::assertGreaterThan($freezing->score, $mild->score);
        self::assertGreaterThan(0, $freezing->score, 'Even a harsh day does not zero the score.');
    }
}
