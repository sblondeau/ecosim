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
use App\Domain\Finance\Work\ThermalCurtainsWork;
use PHPUnit\Framework\TestCase;

final class ThermalCurtainsWorkTest extends TestCase
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
        $work = new ThermalCurtainsWork();

        self::assertSame('thermal_curtains', $work->slug());
        self::assertSame(SceneSlot::Living, $work->slot());
        self::assertFalse($work->qualifiesForEnergyAid(), 'Too small a gesture to qualify for public money.');
    }

    public function testOffersThermalCurtainsWhenNotDone(): void
    {
        $offer = new ThermalCurtainsWork()->offerFor(self::barePassoire());

        self::assertNotNull($offer);
        self::assertSame('Rideaux thermiques', $offer->title);
        self::assertSame(12000, $offer->cost->cents);
        self::assertTrue($offer->resultingHousehold->envelope->thermalCurtains);
    }

    public function testOffersNothingOnceDone(): void
    {
        $done = self::barePassoire()->withEnvelope(
            self::barePassoire()->envelope->withThermalCurtains(true),
        );

        self::assertNull(new ThermalCurtainsWork()->offerFor($done));
    }

    public function testAdviceIsHonestAboutTheSmallLever(): void
    {
        $advice = new ThermalCurtainsWork()->adviceFor(self::barePassoire());

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Geste bon marché : un peu de confort près des fenêtres la nuit. Petit levier, pas un substitut à l\'isolation.', $advice->message);
    }

    public function testDoneLabelAndSceneLayerAppearOnlyOnceDone(): void
    {
        $work = new ThermalCurtainsWork();
        $done = self::barePassoire()->withEnvelope(
            self::barePassoire()->envelope->withThermalCurtains(true),
        );

        self::assertNull($work->doneLabelFor(self::barePassoire()));
        self::assertNull($work->sceneLayerFor(self::barePassoire()));
        self::assertSame('Rideaux thermiques', $work->doneLabelFor($done));
        self::assertSame('curtains', $work->sceneLayerFor($done));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new ThermalCurtainsWork()->iconAsset());
    }
}
