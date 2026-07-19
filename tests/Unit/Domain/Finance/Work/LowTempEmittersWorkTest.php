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
use App\Domain\Finance\Work\LowTempEmittersWork;
use PHPUnit\Framework\TestCase;

final class LowTempEmittersWorkTest extends TestCase
{
    private static function household(HeatingSystem $heatingSystem, bool $lowTempEmitters = false): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(false, WallInsulation::None, Glazing::Single),
            heatingSystem: $heatingSystem,
            lowTempEmitters: $lowTempEmitters,
        );
    }

    public function testIdentity(): void
    {
        $work = new LowTempEmittersWork();

        self::assertSame('low_temp_emitters', $work->slug());
        self::assertSame(SceneSlot::Heating, $work->slot());
        self::assertTrue($work->isEnergyPerformanceWork());
    }

    public function testOffersLowTempEmittersWhenNotInstalled(): void
    {
        $offer = new LowTempEmittersWork()->offerFor(self::household(HeatingSystem::HeatPump));

        self::assertNotNull($offer);
        self::assertSame('Émetteurs basse température', $offer->title);
        self::assertSame(650000, $offer->cost->cents);
        self::assertTrue($offer->resultingHousehold->lowTempEmitters);
    }

    public function testOffersNothingOnceInstalled(): void
    {
        $installed = self::household(HeatingSystem::HeatPump, lowTempEmitters: true);

        self::assertNull(new LowTempEmittersWork()->offerFor($installed));
    }

    public function testAdvisesTheScopGainWithAHeatPump(): void
    {
        $advice = new LowTempEmittersWork()->adviceFor(self::household(HeatingSystem::HeatPump));

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Fait passer le SCOP de votre PAC de ~2,5 à ~4,3 : moins d\'électricité pour la même chaleur.', $advice->message);
    }

    public function testAdvisesLittleEffectWithoutAHeatPump(): void
    {
        $advice = new LowTempEmittersWork()->adviceFor(self::household(HeatingSystem::FuelOilBoiler));

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Utile surtout avec une pompe à chaleur (améliore fortement son rendement) ; sans effet sur une chaudière.', $advice->message);
    }

    public function testDoneLabelAndSceneLayerAppearOnlyOnceInstalled(): void
    {
        $work = new LowTempEmittersWork();
        $installed = self::household(HeatingSystem::HeatPump, lowTempEmitters: true);

        self::assertNull($work->doneLabelFor(self::household(HeatingSystem::HeatPump)));
        self::assertNull($work->sceneLayerFor(self::household(HeatingSystem::HeatPump)));
        self::assertSame('Émetteurs basse température', $work->doneLabelFor($installed));
        self::assertSame('floor-heating', $work->sceneLayerFor($installed));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new LowTempEmittersWork()->iconAsset());
    }
}
