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
use App\Domain\Finance\Work\SolarPanelsWork;
use PHPUnit\Framework\TestCase;

final class SolarPanelsWorkTest extends TestCase
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
        $work = new SolarPanelsWork();

        self::assertSame('solar_panels', $work->slug());
        self::assertSame(SceneSlot::Roof, $work->slot());
        self::assertFalse($work->isEnergyPerformanceWork(), 'Production equipment is not covered by the prime.');
    }

    public function testOffersTheFullInstallFromABareRoof(): void
    {
        $offer = new SolarPanelsWork()->offerFor(self::household(0.0));

        self::assertNotNull($offer);
        self::assertSame('Panneaux solaires 3 kWc', $offer->title);
        self::assertSame(750000, $offer->cost->cents);
        self::assertSame(3.0, $offer->resultingHousehold->solarKwc);
    }

    /**
     * The gate is the full install's own power, not zero: this also offers
     * the full install as the upgrade path from the plug-and-play kit.
     */
    public function testOffersTheFullInstallAsAnUpgradeFromTheKit(): void
    {
        $offer = new SolarPanelsWork()->offerFor(self::household(0.9));

        self::assertNotNull($offer);
        self::assertSame(3.0, $offer->resultingHousehold->solarKwc);
    }

    public function testOffersNothingOnceTheFullInstallIsReached(): void
    {
        self::assertNull(new SolarPanelsWork()->offerFor(self::household(3.0)));
    }

    public function testAdvisesItPaysOffMoreOnceHeatingNeedsAreReduced(): void
    {
        $advice = new SolarPanelsWork()->adviceFor(self::household(0.0));

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Réduit la facture d\'électricité. Plus rentable une fois les besoins de chauffage réduits.', $advice->message);
    }

    /**
     * With a kit already installed, the roof supersedes it — the two cannot
     * share one delivery point once the roof sells its surplus — so the
     * player must be cautioned that the kit is scrapped, not kept.
     */
    public function testCautionsThatTheRoofReplacesAnInstalledKit(): void
    {
        $advice = new SolarPanelsWork()->adviceFor(self::household(0.9));

        self::assertSame(AdviceLevel::Caution, $advice->level);
        self::assertStringContainsString('remplace', $advice->message);
        self::assertStringContainsString('kit', $advice->message);
    }

    public function testDoneLabelAndSceneLayerAppearOnlyOnceTheFullInstallIsDone(): void
    {
        $work = new SolarPanelsWork();

        self::assertNull($work->doneLabelFor(self::household(0.0)));
        self::assertNull($work->sceneLayerFor(self::household(0.0)));
        self::assertNull($work->doneLabelFor(self::household(0.9)), 'A kit alone is not the full install.');
        self::assertNull($work->sceneLayerFor(self::household(0.9)));
        self::assertSame('Panneaux solaires · 3 kWc', $work->doneLabelFor(self::household(3.0)));
        self::assertSame('solar-full', $work->sceneLayerFor(self::household(3.0)));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new SolarPanelsWork()->iconAsset());
    }
}
