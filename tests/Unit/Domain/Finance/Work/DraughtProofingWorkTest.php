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
use App\Domain\Finance\Work\DraughtProofingWork;
use PHPUnit\Framework\TestCase;

final class DraughtProofingWorkTest extends TestCase
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
        $work = new DraughtProofingWork();

        self::assertSame('draught_proofing', $work->slug());
        self::assertSame(SceneSlot::Living, $work->slot());
        self::assertFalse($work->isEnergyPerformanceWork(), 'Too small a gesture to qualify for public money.');
    }

    public function testOffersDraughtProofingWhenNotDone(): void
    {
        $offer = new DraughtProofingWork()->offerFor(self::barePassoire());

        self::assertNotNull($offer);
        self::assertSame('Calfeutrage / joints', $offer->title);
        self::assertSame(8000, $offer->cost->cents);
        self::assertTrue($offer->resultingHousehold->envelope->draughtProofed);
    }

    public function testOffersNothingOnceDone(): void
    {
        $done = self::barePassoire()->withEnvelope(
            self::barePassoire()->envelope->withDraughtProofed(true),
        );

        self::assertNull(new DraughtProofingWork()->offerFor($done));
    }

    public function testAdviceIsHonestAboutTheSmallLever(): void
    {
        $advice = new DraughtProofingWork()->adviceFor(self::barePassoire());

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Geste bon marché : coupe les courants d\'air (quelques % de pertes). Utile en complément — pas un gros levier.', $advice->message);
    }

    public function testDoneLabelAppearsOnlyOnceDone(): void
    {
        $work = new DraughtProofingWork();
        $done = self::barePassoire()->withEnvelope(
            self::barePassoire()->envelope->withDraughtProofed(true),
        );

        self::assertNull($work->doneLabelFor(self::barePassoire()));
        self::assertSame('Calfeutrage / joints', $work->doneLabelFor($done));
    }

    /**
     * The only work with no visual at all: window seals are invisible at this
     * scale. A deliberate exception, identified in tranche 7 — not a gap.
     */
    public function testHasNoSceneLayer(): void
    {
        $done = self::barePassoire()->withEnvelope(
            self::barePassoire()->envelope->withDraughtProofed(true),
        );

        self::assertNull(new DraughtProofingWork()->sceneLayerFor($done));
        self::assertNotNull(new DraughtProofingWork()->doneLabelFor($done), 'but the drawer still shows the chip');
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new DraughtProofingWork()->iconAsset());
    }
}
