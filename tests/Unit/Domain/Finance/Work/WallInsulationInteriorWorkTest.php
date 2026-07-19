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
use App\Domain\Finance\Work\WallInsulationInteriorWork;
use PHPUnit\Framework\TestCase;

final class WallInsulationInteriorWorkTest extends TestCase
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
        $work = new WallInsulationInteriorWork();

        self::assertSame('wall_insulation_interior', $work->slug());
        self::assertSame(SceneSlot::Walls, $work->slot());
        self::assertTrue($work->isEnergyPerformanceWork());
    }

    public function testOffersItiWhenWallsAreBare(): void
    {
        $offer = new WallInsulationInteriorWork()->offerFor(self::household(WallInsulation::None));

        self::assertNotNull($offer);
        self::assertSame('Isolation des murs — intérieure (ITI)', $offer->title);
        self::assertGreaterThan(0, $offer->cost->cents);
        self::assertSame(WallInsulation::Interior, $offer->resultingHousehold->envelope->walls);
    }

    public function testOffersNothingOnceItiIsDone(): void
    {
        self::assertNull(new WallInsulationInteriorWork()->offerFor(self::household(WallInsulation::Interior)));
    }

    /**
     * ITI and ITE are mutually exclusive: once walls carry EITHER variant, no
     * more wall-insulation offer, whichever one was chosen.
     */
    public function testOffersNothingOnceExteriorInsulationIsDone(): void
    {
        self::assertNull(new WallInsulationInteriorWork()->offerFor(self::household(WallInsulation::Exterior)));
    }

    public function testAdvisesTheTradeOff(): void
    {
        $advice = new WallInsulationInteriorWork()->adviceFor(self::household(WallInsulation::None));

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('ITI : moins chère, mais grignote la surface habitable et laisse des ponts thermiques.', $advice->message);
    }

    public function testDoneLabelAndSceneLayerAppearOnlyOnceDone(): void
    {
        $work = new WallInsulationInteriorWork();

        self::assertNull($work->doneLabelFor(self::household(WallInsulation::None)));
        self::assertNull($work->sceneLayerFor(self::household(WallInsulation::None)));
        self::assertSame('Intérieure (ITI)', $work->doneLabelFor(self::household(WallInsulation::Interior)));
        self::assertSame('walls-interior', $work->sceneLayerFor(self::household(WallInsulation::Interior)));
    }

    public function testDoneLabelAndSceneLayerStayNullWhenExteriorWasChosenInstead(): void
    {
        $work = new WallInsulationInteriorWork();

        self::assertNull($work->doneLabelFor(self::household(WallInsulation::Exterior)));
        self::assertNull($work->sceneLayerFor(self::household(WallInsulation::Exterior)));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new WallInsulationInteriorWork()->iconAsset());
    }
}
