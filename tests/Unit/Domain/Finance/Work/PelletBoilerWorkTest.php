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
use App\Domain\Finance\Work\PelletBoilerWork;
use PHPUnit\Framework\TestCase;

final class PelletBoilerWorkTest extends TestCase
{
    private static function household(HeatingSystem $heatingSystem): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(false, WallInsulation::None, Glazing::Single),
            heatingSystem: $heatingSystem,
        );
    }

    public function testIdentity(): void
    {
        $work = new PelletBoilerWork();

        self::assertSame('pellet_boiler', $work->slug());
        self::assertSame(SceneSlot::Heating, $work->slot());
        self::assertTrue($work->isEnergyPerformanceWork());
    }

    public function testOffersAPelletBoilerToAFuelOilHouse(): void
    {
        $offer = new PelletBoilerWork()->offerFor(self::household(HeatingSystem::FuelOilBoiler));

        self::assertNotNull($offer);
        self::assertSame('Chaudière à granulés', $offer->title);
        self::assertSame(1400000, $offer->cost->cents);
        self::assertSame(HeatingSystem::PelletBoiler, $offer->resultingHousehold->heatingSystem);
    }

    public function testOffersNothingOnceInstalled(): void
    {
        self::assertNull(new PelletBoilerWork()->offerFor(self::household(HeatingSystem::PelletBoiler)));
    }

    public function testAdvisesAboutTheFuelAndTheManualLoading(): void
    {
        $advice = new PelletBoilerWork()->adviceFor(self::household(HeatingSystem::FuelOilBoiler));

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Combustible bon marché et bas carbone (~30 g/kWh), mais manuel : stockage et chargement du silo.', $advice->message);
    }

    public function testDoneLabelAndSceneLayerAppearOnlyOnceInstalled(): void
    {
        $work = new PelletBoilerWork();

        self::assertNull($work->doneLabelFor(self::household(HeatingSystem::FuelOilBoiler)));
        self::assertNull($work->sceneLayerFor(self::household(HeatingSystem::FuelOilBoiler)));
        self::assertSame('Chaudière à granulés', $work->doneLabelFor(self::household(HeatingSystem::PelletBoiler)));
        self::assertSame('heating-pellet', $work->sceneLayerFor(self::household(HeatingSystem::PelletBoiler)));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new PelletBoilerWork()->iconAsset());
    }
}
