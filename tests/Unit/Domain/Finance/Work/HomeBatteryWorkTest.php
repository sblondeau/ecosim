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
use App\Domain\Finance\Work\HomeBatteryWork;
use PHPUnit\Framework\TestCase;

final class HomeBatteryWorkTest extends TestCase
{
    private static function barePassoire(): Household
    {
        return self::household(0.0, 0.0);
    }

    private static function household(float $solarKwc, float $batteryKwh): Household
    {
        return new Household(
            solarKwc: $solarKwc,
            batteryKwh: $batteryKwh,
            envelope: new EnvelopeState(false, WallInsulation::None, Glazing::Single),
            heatingSystem: HeatingSystem::FuelOilBoiler,
        );
    }

    public function testIdentity(): void
    {
        $work = new HomeBatteryWork();

        self::assertSame('home_battery', $work->slug());
        self::assertSame(SceneSlot::Garage, $work->slot());
        self::assertFalse($work->qualifiesForEnergyAid(), 'Production/storage equipment is not covered by the prime.');
    }

    public function testOffersNoBatteryBeforeAnySolarIsInstalled(): void
    {
        self::assertNull(new HomeBatteryWork()->offerFor(self::barePassoire()));
    }

    public function testOffersTheBatteryOnceSolarIsInstalled(): void
    {
        $offer = new HomeBatteryWork()->offerFor(self::household(3.0, 0.0));

        self::assertNotNull($offer);
        self::assertSame('Batterie domestique 5 kWh', $offer->title);
        self::assertSame(500000, $offer->cost->cents);
        self::assertSame(5.0, $offer->resultingHousehold->batteryKwh);
    }

    public function testOffersNothingOnceTheBatteryIsInstalled(): void
    {
        self::assertNull(new HomeBatteryWork()->offerFor(self::household(3.0, 5.0)));
    }

    public function testAdvisesItStoresTheSolarSurplusForTheEvening(): void
    {
        $advice = new HomeBatteryWork()->adviceFor(self::household(3.0, 0.0));

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Stocke le surplus solaire pour le consommer le soir.', $advice->message);
    }

    public function testDoneLabelAndSceneLayerAppearOnlyOnceInstalled(): void
    {
        $work = new HomeBatteryWork();

        self::assertNull($work->doneLabelFor(self::household(3.0, 0.0)));
        self::assertNull($work->sceneLayerFor(self::household(3.0, 0.0)));
        self::assertSame('Batterie 5 kWh', $work->doneLabelFor(self::household(3.0, 5.0)));
        self::assertSame('battery', $work->sceneLayerFor(self::household(3.0, 5.0)));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new HomeBatteryWork()->iconAsset());
    }
}
