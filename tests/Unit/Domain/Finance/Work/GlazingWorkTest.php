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
use App\Domain\Finance\Work\GlazingWork;
use PHPUnit\Framework\TestCase;

final class GlazingWorkTest extends TestCase
{
    private static function barePassoire(): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(false, WallInsulation::None, Glazing::Single),
            heatingSystem: HeatingSystem::FuelOilBoiler,
        );
    }

    private static function wellInsulated(Glazing $glazing): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(true, WallInsulation::Interior, $glazing),
            heatingSystem: HeatingSystem::FuelOilBoiler,
        );
    }

    public function testIdentity(): void
    {
        $work = new GlazingWork();

        self::assertSame('glazing', $work->slug());
        self::assertSame(SceneSlot::Walls, $work->slot());
        self::assertTrue($work->isEnergyPerformanceWork());
    }

    public function testOffersDoubleGlazingFromSingle(): void
    {
        $offer = new GlazingWork()->offerFor(self::barePassoire());

        self::assertNotNull($offer);
        self::assertSame('Menuiseries — Double vitrage', $offer->title);
        self::assertSame(800000, $offer->cost->cents);
        self::assertSame(Glazing::Double, $offer->resultingHousehold->envelope->glazing);
    }

    public function testOffersTripleGlazingFromDouble(): void
    {
        $offer = new GlazingWork()->offerFor(self::wellInsulated(Glazing::Double));

        self::assertNotNull($offer);
        self::assertSame('Menuiseries — Triple vitrage', $offer->title);
        self::assertSame(800000, $offer->cost->cents);
        self::assertSame(Glazing::Triple, $offer->resultingHousehold->envelope->glazing);
    }

    public function testOffersNothingOnceTripleGlazingIsReached(): void
    {
        self::assertNull(new GlazingWork()->offerFor(self::wellInsulated(Glazing::Triple)));
    }

    /**
     * Glazing is the one work where doneLabelFor() and offerFor() both answer
     * non-null at the same time: double glazing is done AND upgradeable to
     * triple. Locks that shared state explicitly.
     */
    public function testDoubleGlazingIsBothDoneAndUpgradeable(): void
    {
        $work = new GlazingWork();
        $house = self::barePassoire()->withEnvelope(
            self::barePassoire()->envelope->withGlazing(Glazing::Double),
        );

        self::assertNotNull($work->doneLabelFor($house), 'double glazing shows a done chip');
        self::assertNotNull($work->offerFor($house), 'and still offers the triple upgrade');
        self::assertSame('glazing-double', $work->sceneLayerFor($house));
    }

    /**
     * One of the game's three cautions: glazing weighs little (~10 % of
     * losses) — prioritise roof and walls first in a poorly-insulated house.
     */
    public function testCautionsAgainstPrioritisingGlazingInAPoorlyInsulatedHouse(): void
    {
        $advice = new GlazingWork()->adviceFor(self::barePassoire());

        self::assertSame(AdviceLevel::Caution, $advice->level);
        self::assertSame('Le vitrage pèse peu (~10 % des pertes) : priorisez d\'abord combles et murs.', $advice->message);
    }

    public function testAdvisesComfortAndAcousticsInAWellInsulatedHouse(): void
    {
        $advice = new GlazingWork()->adviceFor(self::wellInsulated(Glazing::Single));

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Complète l\'isolation ; gagne surtout du confort (paroi froide) et de l\'acoustique. Le triple n\'est utile qu\'en climat froid.', $advice->message);
    }

    public function testDoneLabelAndSceneLayerAreNullInSingleGlazing(): void
    {
        $work = new GlazingWork();

        self::assertNull($work->doneLabelFor(self::barePassoire()));
        self::assertNull($work->sceneLayerFor(self::barePassoire()));
    }

    public function testDoneLabelAndSceneLayerInTripleGlazing(): void
    {
        $work = new GlazingWork();
        $house = self::wellInsulated(Glazing::Triple);

        self::assertSame('Triple vitrage', $work->doneLabelFor($house));
        self::assertSame('glazing-triple', $work->sceneLayerFor($house));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new GlazingWork()->iconAsset());
    }
}
