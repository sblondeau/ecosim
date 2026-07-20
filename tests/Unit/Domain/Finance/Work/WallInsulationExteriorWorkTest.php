<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance\Work;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\SceneSlot;
use App\Domain\Finance\Work\WallInsulationExteriorWork;
use PHPUnit\Framework\TestCase;

final class WallInsulationExteriorWorkTest extends TestCase
{
    private static function household(WallInsulation $walls): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(false, $walls, Glazing::Single),
            heatingSystem: HeatingSystem::FuelOilBoiler,
        );
    }

    public function testIdentity(): void
    {
        $work = new WallInsulationExteriorWork();

        self::assertSame('wall_insulation_exterior', $work->slug());
        self::assertSame(SceneSlot::Walls, $work->slot());
        self::assertTrue($work->isEnergyPerformanceWork());
    }

    public function testOffersIteWhenWallsAreBare(): void
    {
        $offer = new WallInsulationExteriorWork()->offerFor(self::household(WallInsulation::None));

        self::assertNotNull($offer);
        self::assertSame('Isolation des murs — extérieure (ITE)', $offer->title);
        self::assertSame(1800000, $offer->cost->cents);
        self::assertSame(WallInsulation::Exterior, $offer->resultingHousehold->envelope->walls);
    }

    public function testOffersNothingOnceIteIsDone(): void
    {
        self::assertNull(new WallInsulationExteriorWork()->offerFor(self::household(WallInsulation::Exterior)));
    }

    /**
     * ITI and ITE are mutually exclusive: once walls carry EITHER variant, no
     * more wall-insulation offer, whichever one was chosen.
     */
    public function testOffersNothingOnceInteriorInsulationIsDone(): void
    {
        self::assertNull(new WallInsulationExteriorWork()->offerFor(self::household(WallInsulation::Interior)));
    }

    public function testAdvisesTheTradeOff(): void
    {
        $advice = new WallInsulationExteriorWork()->adviceFor(self::household(WallInsulation::None));

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('ITE : plus chère, mais meilleure (pas de pont thermique) et ravale la façade.', $advice->message);
    }

    public function testDoneLabelAndSceneLayerAppearOnlyOnceDone(): void
    {
        $work = new WallInsulationExteriorWork();

        self::assertNull($work->doneLabelFor(self::household(WallInsulation::None)));
        self::assertNull($work->sceneLayerFor(self::household(WallInsulation::None)));
        self::assertSame('Murs — Extérieure (ITE)', $work->doneLabelFor(self::household(WallInsulation::Exterior)));
        self::assertSame('walls-exterior', $work->sceneLayerFor(self::household(WallInsulation::Exterior)));
    }

    public function testDoneLabelAndSceneLayerStayNullWhenInteriorWasChosenInstead(): void
    {
        $work = new WallInsulationExteriorWork();

        self::assertNull($work->doneLabelFor(self::household(WallInsulation::Interior)));
        self::assertNull($work->sceneLayerFor(self::household(WallInsulation::Interior)));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new WallInsulationExteriorWork()->iconAsset());
    }
}
