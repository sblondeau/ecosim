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
use App\Domain\Finance\Work\RoofInsulationWork;
use PHPUnit\Framework\TestCase;

final class RoofInsulationWorkTest extends TestCase
{
    private static function household(bool $roofInsulated): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState($roofInsulated, WallInsulation::None, Glazing::Single),
            heatingSystem: HeatingSystem::FuelOilBoiler,
        );
    }

    public function testIdentity(): void
    {
        $work = new RoofInsulationWork();

        self::assertSame('roof_insulation', $work->slug());
        self::assertSame(SceneSlot::Walls, $work->slot());
        self::assertTrue($work->isEnergyPerformanceWork());
    }

    public function testOffersRoofInsulationWhenNotDone(): void
    {
        $offer = new RoofInsulationWork()->offerFor(self::household(false));

        self::assertNotNull($offer);
        self::assertSame('Isolation des combles', $offer->title);
        self::assertGreaterThan(0, $offer->cost->cents);
        self::assertTrue($offer->resultingHousehold->envelope->roofInsulated);
    }

    public function testOffersNothingOnceDone(): void
    {
        self::assertNull(new RoofInsulationWork()->offerFor(self::household(true)));
    }

    public function testAdvisesTheBestValueForMoney(): void
    {
        $advice = new RoofInsulationWork()->adviceFor(self::household(false));

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Souvent le meilleur rapport gain/prix : ~24 % des pertes, et peu cher.', $advice->message);
    }

    public function testDoneLabelAndSceneLayerAppearOnlyOnceDone(): void
    {
        $work = new RoofInsulationWork();

        self::assertNull($work->doneLabelFor(self::household(false)));
        self::assertNull($work->sceneLayerFor(self::household(false)));
        self::assertSame('Isolation des combles', $work->doneLabelFor(self::household(true)));
        self::assertSame('roof-ins', $work->sceneLayerFor(self::household(true)));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new RoofInsulationWork()->iconAsset());
    }
}
