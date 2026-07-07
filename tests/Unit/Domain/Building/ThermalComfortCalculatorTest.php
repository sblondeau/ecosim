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

    public function testScoreDegradesProgressivelyNeverACliff(): void
    {
        $calculator = new ThermalComfortCalculator();

        $mild = $calculator->comfortFor(InsulationLevel::Original, 10.0);
        $freezing = $calculator->comfortFor(InsulationLevel::Original, -5.0);

        self::assertGreaterThan($freezing->score, $mild->score);
        self::assertGreaterThan(0, $freezing->score, 'Even a harsh day does not zero the score.');
    }
}
