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
use App\Domain\Finance\Work\BoilerRepairWork;
use PHPUnit\Framework\TestCase;

final class BoilerRepairWorkTest extends TestCase
{
    private static function household(bool $boilerBroken): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(false, WallInsulation::None, Glazing::Single),
            heatingSystem: HeatingSystem::FuelOilBoiler,
            boilerBroken: $boilerBroken,
        );
    }

    public function testIdentity(): void
    {
        $work = new BoilerRepairWork();

        self::assertSame('boiler_repair', $work->slug());
        self::assertSame(SceneSlot::Heating, $work->slot());
        self::assertFalse($work->isEnergyPerformanceWork());
    }

    public function testOffersARepairWhenTheBoilerIsBroken(): void
    {
        $offer = new BoilerRepairWork()->offerFor(self::household(true));

        self::assertNotNull($offer);
        self::assertSame('Réparer la chaudière fioul', $offer->title);
        self::assertSame(150000, $offer->cost->cents);
        self::assertFalse($offer->resultingHousehold->boilerBroken);
    }

    public function testOffersNothingWhenTheBoilerIsNotBroken(): void
    {
        self::assertNull(new BoilerRepairWork()->offerFor(self::household(false)));
    }

    public function testAdvisesStayingOnFuelOil(): void
    {
        $advice = new BoilerRepairWork()->adviceFor(self::household(true));

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Rapide et peu cher, mais vous restez au fioul (facture et CO₂ élevés).', $advice->message);
    }

    public function testDoneLabelAndSceneLayerAreAlwaysNull(): void
    {
        $work = new BoilerRepairWork();

        self::assertNull($work->doneLabelFor(self::household(true)));
        self::assertNull($work->doneLabelFor(self::household(false)));
        self::assertNull($work->sceneLayerFor(self::household(true)));
        self::assertNull($work->sceneLayerFor(self::household(false)));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new BoilerRepairWork()->iconAsset());
    }
}
