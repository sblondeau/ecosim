<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\BuildingCalibration;
use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\Ventilation;
use App\Domain\Building\WallInsulation;
use PHPUnit\Framework\TestCase;

final class BuildingCalibrationTest extends TestCase
{
    private BuildingCalibration $calibration;

    protected function setUp(): void
    {
        $this->calibration = new BuildingCalibration();
    }

    public function testOriginalEnvelopeKeepsFullLoss(): void
    {
        $original = new EnvelopeState(false, WallInsulation::None, Glazing::Single);

        self::assertSame(1.0, $this->calibration->envelopeLossFactor($original));
    }

    public function testEachSurfaceRemovesItsSourcedShare(): void
    {
        $roofOnly = new EnvelopeState(true, WallInsulation::None, Glazing::Single);
        self::assertEqualsWithDelta(0.76, $this->calibration->envelopeLossFactor($roofOnly), 1e-9);

        $roofItiDouble = new EnvelopeState(true, WallInsulation::Interior, Glazing::Double);
        self::assertEqualsWithDelta(0.535, $this->calibration->envelopeLossFactor($roofItiDouble), 1e-9);
    }

    public function testCeilingIsAboutHalfWithoutVmc(): void
    {
        $best = new EnvelopeState(true, WallInsulation::Exterior, Glazing::Triple);
        self::assertEqualsWithDelta(0.50, $this->calibration->envelopeLossFactor($best), 1e-9);
    }

    public function testDoubleFlowVentilationRecoversHeat(): void
    {
        $bare = new EnvelopeState(false, WallInsulation::None, Glazing::Single, Ventilation::None);
        $vmc = new EnvelopeState(false, WallInsulation::None, Glazing::Single, Ventilation::DoubleFlow);
        self::assertEqualsWithDelta(1.0, $this->calibration->envelopeLossFactor($bare), 1e-9);
        self::assertEqualsWithDelta(0.86, $this->calibration->envelopeLossFactor($vmc), 1e-9); // 1 − 0,14
    }

    public function testVentilationReopensThePathBelowHalf(): void
    {
        $full = new EnvelopeState(true, WallInsulation::Exterior, Glazing::Triple, Ventilation::DoubleFlow);
        self::assertEqualsWithDelta(0.36, $this->calibration->envelopeLossFactor($full), 1e-9); // 0,50 − 0,14
    }

    public function testColdWallPenaltyDropsWithWallsAndGlazing(): void
    {
        $original = new EnvelopeState(false, WallInsulation::None, Glazing::Single);
        self::assertEqualsWithDelta(0.15, $this->calibration->coldWallPenaltyFactor($original), 1e-9);

        $wallsOnly = new EnvelopeState(false, WallInsulation::Interior, Glazing::Single);
        self::assertEqualsWithDelta(0.07, $this->calibration->coldWallPenaltyFactor($wallsOnly), 1e-9);

        $best = new EnvelopeState(true, WallInsulation::Exterior, Glazing::Triple);
        self::assertEqualsWithDelta(0.02, $this->calibration->coldWallPenaltyFactor($best), 1e-9); // planché
    }

    public function testDraughtProofingRemovesASmallShareOfLoss(): void
    {
        $bare = new EnvelopeState(false, WallInsulation::None, Glazing::Single, Ventilation::None);
        $sealed = $bare->withDraughtProofed(true);
        self::assertEqualsWithDelta(1.0, $this->calibration->envelopeLossFactor($bare), 1e-9);
        self::assertEqualsWithDelta(0.96, $this->calibration->envelopeLossFactor($sealed), 1e-9); // 1 − 0,04
    }

    public function testThermalCurtainsEaseTheColdWallSlightly(): void
    {
        $bare = new EnvelopeState(false, WallInsulation::None, Glazing::Single, Ventilation::None);
        $curtained = $bare->withThermalCurtains(true);
        // base 0,15 → 0,13 (les rideaux retirent 0,02 ; plancher 0,02 non atteint)
        self::assertEqualsWithDelta(0.15, $this->calibration->coldWallPenaltyFactor($bare), 1e-9);
        self::assertEqualsWithDelta(0.13, $this->calibration->coldWallPenaltyFactor($curtained), 1e-9);
    }
}
