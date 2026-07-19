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
use App\Domain\Finance\Work\SolarKitWork;
use PHPUnit\Framework\TestCase;

final class SolarKitWorkTest extends TestCase
{
    private static function household(float $solarKwc): Household
    {
        return new Household(
            solarKwc: $solarKwc,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(false, WallInsulation::None, Glazing::Single),
            heatingSystem: HeatingSystem::FuelOilBoiler,
        );
    }

    public function testIdentity(): void
    {
        $work = new SolarKitWork();

        self::assertSame('solar_kit', $work->slug());
        self::assertSame(SceneSlot::Garage, $work->slot());
        self::assertFalse($work->isEnergyPerformanceWork(), 'Production equipment is not covered by the prime.');
    }

    public function testOffersTheKitFromABareRoof(): void
    {
        $offer = new SolarKitWork()->offerFor(self::household(0.0));

        self::assertNotNull($offer);
        self::assertSame('Kit solaire plug-and-play 0.9 kWc', $offer->title);
        self::assertSame(80000, $offer->cost->cents);
        self::assertSame(0.9, $offer->resultingHousehold->solarKwc);
    }

    /**
     * The kit is the cheap entry point, superseded by the full install: once
     * anything is already installed (kit or full), it is not offered again.
     */
    public function testOffersNothingOnceAnySolarIsInstalled(): void
    {
        self::assertNull(new SolarKitWork()->offerFor(self::household(0.9)));
        self::assertNull(new SolarKitWork()->offerFor(self::household(3.0)));
    }

    public function testAdvisesTheAccessibleFirstStep(): void
    {
        $advice = new SolarKitWork()->adviceFor(self::household(0.0));

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Le premier pas accessible : sans installateur ni aide, rendement modeste.', $advice->message);
    }

    public function testDoneLabelAndSceneLayerAppearOnlyWhileAtTheKitStage(): void
    {
        $work = new SolarKitWork();

        self::assertNull($work->doneLabelFor(self::household(0.0)));
        self::assertNull($work->sceneLayerFor(self::household(0.0)));
        self::assertSame('Kit solaire plug-and-play 0.9 kWc', $work->doneLabelFor(self::household(0.9)));
        self::assertSame('solar-kit', $work->sceneLayerFor(self::household(0.9)));
        self::assertNull($work->doneLabelFor(self::household(3.0)), 'Superseded once upgraded to the full install.');
        self::assertNull($work->sceneLayerFor(self::household(3.0)));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new SolarKitWork()->iconAsset());
    }
}
