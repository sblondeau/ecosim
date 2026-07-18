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
use App\Domain\Finance\Work\HeatPumpWork;
use PHPUnit\Framework\TestCase;

final class HeatPumpWorkTest extends TestCase
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

    public function testIdentity(): void
    {
        $work = new HeatPumpWork();

        self::assertSame('heat_pump', $work->slug());
        self::assertSame(SceneSlot::Heating, $work->slot());
        self::assertTrue($work->isEnergyPerformanceWork());
    }

    public function testOffersAHeatPumpToAFuelOilHouse(): void
    {
        $offer = new HeatPumpWork()->offerFor(self::barePassoire());

        self::assertNotNull($offer);
        self::assertSame('Pompe à chaleur air/eau', $offer->title);
        self::assertGreaterThan(0, $offer->cost->cents);
        self::assertSame(HeatingSystem::HeatPump, $offer->resultingHousehold->heatingSystem);
    }

    public function testOffersNothingOnceInstalled(): void
    {
        $installed = self::barePassoire()->withHeatingSystem(HeatingSystem::HeatPump);

        self::assertNull(new HeatPumpWork()->offerFor($installed));
    }

    /**
     * The one real sequencing mistake this work can hide: a heat pump in a
     * passoire is oversized and the bills stay high. Advice, never a ban.
     */
    public function testCautionsAgainstAHeatPumpInAPoorlyInsulatedHouse(): void
    {
        $advice = new HeatPumpWork()->adviceFor(self::barePassoire());

        self::assertSame(AdviceLevel::Caution, $advice->level);
    }

    public function testDoneLabelAndSceneLayerAppearOnlyOnceInstalled(): void
    {
        $work = new HeatPumpWork();
        $installed = self::barePassoire()->withHeatingSystem(HeatingSystem::HeatPump);

        self::assertNull($work->doneLabelFor(self::barePassoire()));
        self::assertNull($work->sceneLayerFor(self::barePassoire()));
        self::assertSame('Pompe à chaleur', $work->doneLabelFor($installed));
        self::assertSame('heating-heat-pump', $work->sceneLayerFor($installed));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new HeatPumpWork()->iconAsset());
    }
}
