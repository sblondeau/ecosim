<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance\Work;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\Ventilation;
use App\Domain\Building\WallInsulation;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\SceneSlot;
use App\Domain\Finance\Work\VentilationDoubleFlowWork;
use PHPUnit\Framework\TestCase;

final class VentilationDoubleFlowWorkTest extends TestCase
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

    private static function wellInsulated(Ventilation $ventilation = Ventilation::None): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(true, WallInsulation::Interior, Glazing::Double, $ventilation),
            heatingSystem: HeatingSystem::FuelOilBoiler,
        );
    }

    public function testIdentity(): void
    {
        $work = new VentilationDoubleFlowWork();

        self::assertSame('ventilation_double_flow', $work->slug());
        self::assertSame(SceneSlot::Walls, $work->slot());
        self::assertTrue($work->qualifiesForEnergyAid());
    }

    public function testOffersDoubleFlowVentilationWhenNoneInstalled(): void
    {
        $offer = new VentilationDoubleFlowWork()->offerFor(self::barePassoire());

        self::assertNotNull($offer);
        self::assertSame('VMC double flux', $offer->title);
        self::assertSame(600000, $offer->cost->cents);
        self::assertSame(Ventilation::DoubleFlow, $offer->resultingHousehold->envelope->ventilation);
    }

    public function testOffersNothingOnceInstalled(): void
    {
        self::assertNull(new VentilationDoubleFlowWork()->offerFor(self::wellInsulated(Ventilation::DoubleFlow)));
    }

    /**
     * One of the game's three cautions: double-flow ventilation recovers heat
     * from extracted air, so it pays off only once there is heat to recover —
     * install it AFTER the envelope, not before.
     */
    public function testCautionsAgainstInstallingBeforeTheEnvelopeIsInsulated(): void
    {
        $advice = new VentilationDoubleFlowWork()->adviceFor(self::barePassoire());

        self::assertSame(AdviceLevel::Caution, $advice->level);
        self::assertSame('À poser plutôt APRÈS l\'isolation : la VMC double flux récupère la chaleur, autant qu\'il y en ait à récupérer.', $advice->message);
    }

    public function testAdvisesHealthyAirRenewalInAWellInsulatedHouse(): void
    {
        $advice = new VentilationDoubleFlowWork()->adviceFor(self::wellInsulated());

        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Récupère la chaleur de l\'air extrait et renouvelle l\'air sainement.', $advice->message);
    }

    public function testDoneLabelAndSceneLayerAppearOnlyOnceInstalled(): void
    {
        $work = new VentilationDoubleFlowWork();
        $installed = self::wellInsulated(Ventilation::DoubleFlow);

        self::assertNull($work->doneLabelFor(self::barePassoire()));
        self::assertNull($work->sceneLayerFor(self::barePassoire()));
        self::assertSame('VMC double flux', $work->doneLabelFor($installed));
        self::assertSame('vmc-double-flow', $work->sceneLayerFor($installed));
    }

    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.new VentilationDoubleFlowWork()->iconAsset());
    }
}
