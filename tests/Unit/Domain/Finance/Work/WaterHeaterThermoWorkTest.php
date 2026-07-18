<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance\Work;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use App\Domain\Building\WaterHeater;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\SceneSlot;
use App\Domain\Finance\Work\WaterHeaterThermoWork;
use PHPUnit\Framework\TestCase;

final class WaterHeaterThermoWorkTest extends TestCase
{
    private static function household(WaterHeater $waterHeater): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(false, WallInsulation::None, Glazing::Single),
            heatingSystem: HeatingSystem::FuelOilBoiler,
            waterHeater: $waterHeater,
        );
    }

    public function testIdentity(): void
    {
        $work = new WaterHeaterThermoWork();

        self::assertSame('water_heater_thermo', $work->slug());
        self::assertSame(SceneSlot::Heating, $work->slot());
        self::assertTrue($work->isEnergyPerformanceWork());
    }

    public function testOffersAThermodynamicTankWhenElectric(): void
    {
        $offer = new WaterHeaterThermoWork()->offerFor(self::household(WaterHeater::ElectricTank));

        self::assertNotNull($offer);
        self::assertSame('Chauffe-eau thermodynamique', $offer->title);
        self::assertGreaterThan(0, $offer->cost->cents);
        self::assertSame(WaterHeater::Thermodynamic, $offer->resultingHousehold->waterHeater);
    }

    public function testOffersNothingOnceInstalled(): void
    {
        self::assertNull(new WaterHeaterThermoWork()->offerFor(self::household(WaterHeater::Thermodynamic)));
    }

    public function testAdvisesAboutTheHiddenEnergyShare(): void
    {
        $advice = new WaterHeaterThermoWork()->adviceFor(self::household(WaterHeater::ElectricTank));

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('L\'eau chaude = ~15 % de l\'énergie, souvent oubliée : le thermodynamique divise sa conso par ~3.', $advice->message);
    }

    public function testDoneLabelAndSceneLayerAppearOnlyOnceInstalled(): void
    {
        $work = new WaterHeaterThermoWork();

        self::assertNull($work->doneLabelFor(self::household(WaterHeater::ElectricTank)));
        self::assertNull($work->sceneLayerFor(self::household(WaterHeater::ElectricTank)));
        self::assertSame('Chauffe-eau thermodynamique', $work->doneLabelFor(self::household(WaterHeater::Thermodynamic)));
        self::assertSame('water-heater-thermo', $work->sceneLayerFor(self::household(WaterHeater::Thermodynamic)));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new WaterHeaterThermoWork()->iconAsset());
    }
}
