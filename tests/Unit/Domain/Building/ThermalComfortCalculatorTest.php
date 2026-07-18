<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\ThermalComfortCalculator;
use App\Domain\Building\WallInsulation;
use PHPUnit\Framework\TestCase;

final class ThermalComfortCalculatorTest extends TestCase
{
    private function original(): EnvelopeState
    {
        return new EnvelopeState(false, WallInsulation::None, Glazing::Single);
    }

    private function bestEnvelope(): EnvelopeState
    {
        return new EnvelopeState(true, WallInsulation::Exterior, Glazing::Triple);
    }

    public function testHeatingHoldsTheSetpointDuringTheHeatingSeason(): void
    {
        $comfort = new ThermalComfortCalculator()->comfortFor($this->original(), 0.0);

        self::assertSame(19.0, $comfort->indoorC);
    }

    public function testTheHouseFreeRunsOutsideTheHeatingSeason(): void
    {
        $comfort = new ThermalComfortCalculator()->comfortFor($this->original(), 22.0);

        self::assertSame(22.0, $comfort->indoorC);
        self::assertSame(22.0, $comfort->feltC, 'No indoor/outdoor gap, no cold-wall effect.');
        self::assertSame(100, $comfort->score);
    }

    public function testColdWallsMakeThePassoireFeelColderThanItsAir(): void
    {
        // Freezing day, uninsulated: 19 °C of air, felt 19 − 0.15 × 19 ≈ 16.2 °C.
        $comfort = new ThermalComfortCalculator()->comfortFor($this->original(), 0.0);

        self::assertSame(16.2, $comfort->feltC);
        self::assertSame(72, $comfort->score, '2.8 °C below range × 10 pts = 72.');
    }

    public function testInsulationImprovesComfortAtTheSameSetpoint(): void
    {
        $calculator = new ThermalComfortCalculator();

        $passoire = $calculator->comfortFor($this->original(), 0.0);
        $bestEnvelope = $calculator->comfortFor($this->bestEnvelope(), 0.0);

        self::assertGreaterThan($passoire->feltC, $bestEnvelope->feltC);
        self::assertGreaterThan($passoire->score, $bestEnvelope->score);
        // The pedagogical claim (game-design §8): insulation buys comfort, not just smaller bills.
        self::assertGreaterThanOrEqual(94, $bestEnvelope->score);
    }

    public function testAnUnheatedPassoireSettlesJustAboveTheOutdoorTemperature(): void
    {
        // Steady state: 10 kWh of internal gains / 12.5 kWh/°C·day of losses = +0.8 °C.
        $comfort = new ThermalComfortCalculator()->unheatedComfortFor($this->original(), 3.0, 10.0);

        self::assertSame(3.8, $comfort->indoorC);
        self::assertSame(0, $comfort->score, 'Far below the comfort range: the breakdown must hurt.');
    }

    public function testInsulationHoldsMoreOfTheInternalGainsWhenUnheated(): void
    {
        $calculator = new ThermalComfortCalculator();

        $passoire = $calculator->unheatedComfortFor($this->original(), 3.0, 10.0);
        // 10 kWh / (12.5 × 0.50) = +1.6 °C: same gains, twice the temperature rise.
        $bestEnvelope = $calculator->unheatedComfortFor($this->bestEnvelope(), 3.0, 10.0);

        self::assertGreaterThan($passoire->indoorC, $bestEnvelope->indoorC);
        self::assertSame(4.6, $bestEnvelope->indoorC);
    }

    public function testUnheatedIndoorNeverExceedsTheSetpoint(): void
    {
        // A mild shoulder-season day: huge gains cannot warm the house past the setpoint.
        $comfort = new ThermalComfortCalculator()->unheatedComfortFor($this->bestEnvelope(), 17.0, 50.0);

        self::assertSame(19.0, $comfort->indoorC);
    }

    public function testUnheatedFreeRunsLikeHeatedOutsideTheHeatingSeason(): void
    {
        $unheated = new ThermalComfortCalculator()->unheatedComfortFor($this->original(), 22.0, 10.0);

        self::assertSame(22.0, $unheated->indoorC, 'No heating needed: the breakdown changes nothing in summer.');
        self::assertSame(100, $unheated->score);
    }

    public function testALowerSetpointHoldsTheHouseColder(): void
    {
        $calculator = new ThermalComfortCalculator();

        $at21 = $calculator->comfortFor($this->original(), 0.0, 21.0);
        $at19 = $calculator->comfortFor($this->original(), 0.0, 19.0);
        $at16 = $calculator->comfortFor($this->original(), 0.0, 16.0);

        self::assertSame(21.0, $at21->indoorC);
        self::assertSame(19.0, $at19->indoorC);
        self::assertSame(16.0, $at16->indoorC, 'Dialling down the thermostat directly lowers the indoor air.');
        self::assertGreaterThan($at16->score, $at19->score, 'Under-heating to save money costs comfort.');
    }

    public function testScoreDegradesProgressivelyNeverACliff(): void
    {
        $calculator = new ThermalComfortCalculator();

        $mild = $calculator->comfortFor($this->original(), 10.0);
        $freezing = $calculator->comfortFor($this->original(), -5.0);

        self::assertGreaterThan($freezing->score, $mild->score);
        self::assertGreaterThan(0, $freezing->score, 'Even a harsh day does not zero the score.');
    }
}
