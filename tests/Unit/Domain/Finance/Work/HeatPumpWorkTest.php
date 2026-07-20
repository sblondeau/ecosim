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
    private static function barePassoire(bool $boilerBroken = false): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(false, WallInsulation::None, Glazing::Single),
            heatingSystem: HeatingSystem::FuelOilBoiler,
            boilerBroken: $boilerBroken,
        );
    }

    private static function wellInsulated(): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(true, WallInsulation::Interior, Glazing::Single),
            heatingSystem: HeatingSystem::FuelOilBoiler,
        );
    }

    public function testIdentity(): void
    {
        $work = new HeatPumpWork();

        self::assertSame('heat_pump', $work->slug());
        self::assertSame(SceneSlot::Heating, $work->slot());
        self::assertTrue($work->qualifiesForEnergyAid());
    }

    public function testOffersAHeatPumpToAFuelOilHouse(): void
    {
        $offer = new HeatPumpWork()->offerFor(self::barePassoire());

        self::assertNotNull($offer);
        self::assertSame('Pompe à chaleur air/eau', $offer->title);
        self::assertSame(1300000, $offer->cost->cents);
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
        self::assertSame('Maison peu isolée → PAC surdimensionnée, factures qui resteront hautes. Isolez d\'abord.', $advice->message);
    }

    public function testAdvisesLeavingFuelOilDuringABoilerBreakdown(): void
    {
        $advice = new HeatPumpWork()->adviceFor(self::barePassoire(boilerBroken: true));

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('L\'occasion de sortir du fioul. Vérifiez que la maison est un minimum isolée, sinon la PAC sera bridée.', $advice->message);
    }

    public function testAdvisesGoodEfficiencyInAWellInsulatedHouse(): void
    {
        $advice = new HeatPumpWork()->adviceFor(self::wellInsulated());

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Bon rendement attendu : la maison est suffisamment isolée pour une PAC efficace.', $advice->message);
    }

    public function testDoneLabelAppearsOnlyOnceInstalled(): void
    {
        $work = new HeatPumpWork();
        $installed = self::barePassoire()->withHeatingSystem(HeatingSystem::HeatPump);

        self::assertNull($work->doneLabelFor(self::barePassoire()));
        self::assertSame('Pompe à chaleur', $work->doneLabelFor($installed));
    }

    /**
     * Equipment has no envelope CSS layer: its visual is a whole scene
     * component selected by HouseSceneView from the household's equipment
     * state (heatingState), not by a house--* gate. So sceneLayerFor is null.
     */
    public function testHasNoEnvelopeLayer(): void
    {
        $installed = self::barePassoire()->withHeatingSystem(HeatingSystem::HeatPump);

        self::assertNull(new HeatPumpWork()->sceneLayerFor(self::barePassoire()));
        self::assertNull(new HeatPumpWork()->sceneLayerFor($installed));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new HeatPumpWork()->iconAsset());
    }
}
