<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\Ventilation;
use App\Domain\Building\WallInsulation;
use App\Domain\Building\WaterHeater;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\Money;
use App\Domain\Finance\Renovation;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationCatalog;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\RenovationQuoter;
use App\Domain\Finance\SceneSlot;
use PHPUnit\Framework\TestCase;

final class RenovationQuoterTest extends TestCase
{
    private static function barePassoire(): Household
    {
        return new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::FuelOilBoiler);
    }

    public function testRoofInsulationQuotedWhenAtticBare(): void
    {
        $household = self::barePassoire();
        $quote = new RenovationQuoter()->quote(Renovation::RoofInsulation, $household);

        self::assertNotNull($quote);
        self::assertTrue($quote->resultingHousehold->envelope->roofInsulated);
    }

    public function testRoofInsulationHiddenOnceDone(): void
    {
        $household = self::barePassoire()
            ->withEnvelope(new EnvelopeState(true, WallInsulation::None, Glazing::Single));

        self::assertNull(new RenovationQuoter()->quote(Renovation::RoofInsulation, $household));
    }

    public function testWallInsulationExteriorQuotedOnUninsulatedWalls(): void
    {
        $household = self::barePassoire();
        $quote = new RenovationQuoter()->quote(Renovation::WallInsulationExterior, $household);

        self::assertNotNull($quote);
        self::assertSame(WallInsulation::Exterior, $quote->resultingHousehold->envelope->walls);
    }

    public function testWallItiAndIteAreMutuallyExclusive(): void
    {
        $withWalls = self::barePassoire()
            ->withEnvelope(new EnvelopeState(false, WallInsulation::Interior, Glazing::Single));

        $quoter = new RenovationQuoter();
        self::assertNull($quoter->quote(Renovation::WallInsulationInterior, $withWalls));
        self::assertNull($quoter->quote(Renovation::WallInsulationExterior, $withWalls));
    }

    public function testGlazingClimbsSingleToDoubleToTriple(): void
    {
        $quoter = new RenovationQuoter();
        $single = self::barePassoire();
        $doubleQuote = $quoter->quote(Renovation::Glazing, $single);
        self::assertNotNull($doubleQuote);
        self::assertSame(Glazing::Double, $doubleQuote->resultingHousehold->envelope->glazing);

        $atDouble = $single->withEnvelope(new EnvelopeState(false, WallInsulation::None, Glazing::Double));
        $tripleQuote = $quoter->quote(Renovation::Glazing, $atDouble);
        self::assertNotNull($tripleQuote);
        self::assertSame(Glazing::Triple, $tripleQuote->resultingHousehold->envelope->glazing);

        $atTriple = $single->withEnvelope(new EnvelopeState(false, WallInsulation::None, Glazing::Triple));
        self::assertNull($quoter->quote(Renovation::Glazing, $atTriple));
    }

    public function testSubsidisedWorksGetTheIncomeBracketPrime(): void
    {
        // Scenario income 2800 x 12 = 33 600 €/an -> "intermédiaire" bracket, 40 %.
        $quote = new RenovationQuoter()->quote(Renovation::HeatPump, self::barePassoire());

        self::assertNotNull($quote);
        self::assertSame(13000_00, $quote->cost->cents);
        self::assertSame(5200_00, $quote->subsidy->cents);
        self::assertSame(7800_00, $quote->netCost()->cents);
        self::assertSame(HeatingSystem::HeatPump, $quote->resultingHousehold->heatingSystem);
    }

    public function testSolarAndBatteryHaveNoPrime(): void
    {
        $quoter = new RenovationQuoter();

        $solar = $quoter->quote(Renovation::SolarPanels, self::barePassoire());
        self::assertNotNull($solar);
        self::assertSame(0, $solar->subsidy->cents);
        self::assertSame(3.0, $solar->resultingHousehold->solarKwc);

        // The battery only makes sense once panels are installed.
        $battery = $quoter->quote(Renovation::HomeBattery, $solar->resultingHousehold);
        self::assertNotNull($battery);
        self::assertSame(0, $battery->subsidy->cents);
        self::assertSame(5.0, $battery->resultingHousehold->batteryKwh);
    }

    public function testBatteryIsOnlyQuotedOnceSolarIsInstalled(): void
    {
        $quoter = new RenovationQuoter();

        self::assertNull(
            $quoter->quote(Renovation::HomeBattery, self::barePassoire()),
            'No panels yet: a battery would store nothing, so it is not offered.',
        );

        $withSolar = self::barePassoire()->withSolarKwc(3.0);
        $quote = $quoter->quote(Renovation::HomeBattery, $withSolar);

        self::assertNotNull($quote, 'Once panels are installed, the battery becomes a real option.');
        self::assertSame(5.0, $quote->resultingHousehold->batteryKwh);
    }

    public function testSolarKitIsTheCheapEntryPointAndUpgradesToTheFullInstall(): void
    {
        $quoter = new RenovationQuoter();
        $bare = self::barePassoire();

        // Nothing installed yet: both the kit and the full install are on offer.
        $kitQuote = $quoter->quote(Renovation::SolarKit, $bare);
        self::assertNotNull($kitQuote, 'The plug-and-play kit is offered on a bare house.');
        self::assertSame(0.9, $kitQuote->resultingHousehold->solarKwc);
        self::assertSame(800_00, $kitQuote->cost->cents);
        self::assertSame(0, $kitQuote->subsidy->cents, 'No public money for production equipment.');
        self::assertNotNull($quoter->quote(Renovation::SolarPanels, $bare), 'The full install is also on offer from scratch.');

        // Once the kit is installed: the kit is no longer offered, but the full
        // install now appears as an upgrade path.
        $afterKit = $kitQuote->resultingHousehold;
        self::assertNull($quoter->quote(Renovation::SolarKit, $afterKit), 'Already have a kit: not offered again.');
        $upgradeQuote = $quoter->quote(Renovation::SolarPanels, $afterKit);
        self::assertNotNull($upgradeQuote, 'The full install upgrades from the kit.');
        self::assertSame(3.0, $upgradeQuote->resultingHousehold->solarKwc);

        // Once the full install is done: neither is offered any more.
        $afterFull = $upgradeQuote->resultingHousehold;
        self::assertNull($quoter->quote(Renovation::SolarKit, $afterFull));
        self::assertNull($quoter->quote(Renovation::SolarPanels, $afterFull));
    }

    public function testBoilerRepairIsOnlyQuotedWhenBroken(): void
    {
        $quoter = new RenovationQuoter();

        self::assertNull(
            $quoter->quote(Renovation::BoilerRepair, self::barePassoire()),
            'Nothing to repair while the boiler runs.',
        );

        $quote = $quoter->quote(Renovation::BoilerRepair, self::barePassoire()->withBoilerBroken(true));

        self::assertNotNull($quote);
        self::assertSame(1500_00, $quote->cost->cents);
        self::assertSame(0, $quote->subsidy->cents, 'No public money for repairing fossil equipment.');
        self::assertFalse(Renovation::BoilerRepair->isLoanEligible());
        self::assertFalse($quote->resultingHousehold->boilerBroken, 'The repair puts the boiler back to work.');
        self::assertSame(HeatingSystem::FuelOilBoiler, $quote->resultingHousehold->heatingSystem);
    }

    public function testAlreadyDoneWorksAreNotQuoted(): void
    {
        $quoter = new RenovationQuoter();
        $equipped = new Household(3.0, 5.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::HeatPump);

        self::assertNull($quoter->quote(Renovation::SolarKit, $equipped));
        self::assertNull($quoter->quote(Renovation::SolarPanels, $equipped));
        self::assertNull($quoter->quote(Renovation::HomeBattery, $equipped));
        self::assertNull($quoter->quote(Renovation::HeatPump, $equipped));
    }

    public function testLowTempEmittersQuotedUntilInstalled(): void
    {
        $quoter = new RenovationQuoter();
        $household = self::barePassoire();
        $quote = $quoter->quote(Renovation::LowTempEmitters, $household);

        self::assertNotNull($quote);
        self::assertSame(6500_00, $quote->cost->cents);
        self::assertSame(2600_00, $quote->subsidy->cents, '"Intermédiaire" bracket, 40 %.');
        self::assertTrue($quote->resultingHousehold->lowTempEmitters);

        self::assertNull(
            $quoter->quote(Renovation::LowTempEmitters, $quote->resultingHousehold),
            'Already installed: no longer offered.',
        );
    }

    public function testVentilationDoubleFlowQuotedWhenNoneInstalled(): void
    {
        $quoter = new RenovationQuoter();
        $household = self::barePassoire();
        $quote = $quoter->quote(Renovation::VentilationDoubleFlow, $household);

        self::assertNotNull($quote);
        self::assertSame(6000_00, $quote->cost->cents);
        self::assertSame(2400_00, $quote->subsidy->cents, '"Intermédiaire" bracket, 40 %.');
        self::assertSame(Ventilation::DoubleFlow, $quote->resultingHousehold->envelope->ventilation);

        self::assertNull(
            $quoter->quote(Renovation::VentilationDoubleFlow, $quote->resultingHousehold),
            'Already installed: no longer offered.',
        );
    }

    public function testWaterHeaterThermoQuotedUntilInstalled(): void
    {
        $quoter = new RenovationQuoter();
        $household = self::barePassoire();
        $quote = $quoter->quote(Renovation::WaterHeaterThermo, $household);

        self::assertNotNull($quote);
        self::assertSame(3500_00, $quote->cost->cents);
        self::assertSame(1400_00, $quote->subsidy->cents, '"Intermédiaire" bracket, 40 %.');
        self::assertSame(WaterHeater::Thermodynamic, $quote->resultingHousehold->waterHeater);

        self::assertNull(
            $quoter->quote(Renovation::WaterHeaterThermo, $quote->resultingHousehold),
            'Already thermodynamic: no longer offered.',
        );
    }

    public function testDraughtProofingQuotedUntilDone(): void
    {
        $quoter = new RenovationQuoter();
        $household = self::barePassoire();
        $quote = $quoter->quote(Renovation::DraughtProofing, $household);

        self::assertNotNull($quote);
        self::assertSame(80_00, $quote->cost->cents);
        self::assertSame(0, $quote->subsidy->cents, 'No public money for a cheap gesture.');
        self::assertFalse(Renovation::DraughtProofing->isSubsidised());
        self::assertTrue($quote->resultingHousehold->envelope->draughtProofed);

        self::assertNull(
            $quoter->quote(Renovation::DraughtProofing, $quote->resultingHousehold),
            'Already draught-proofed: no longer offered.',
        );
    }

    public function testThermalCurtainsQuotedUntilDone(): void
    {
        $quoter = new RenovationQuoter();
        $household = self::barePassoire();
        $quote = $quoter->quote(Renovation::ThermalCurtains, $household);

        self::assertNotNull($quote);
        self::assertSame(120_00, $quote->cost->cents);
        self::assertSame(0, $quote->subsidy->cents, 'No public money for a cheap gesture.');
        self::assertFalse(Renovation::ThermalCurtains->isSubsidised());
        self::assertTrue($quote->resultingHousehold->envelope->thermalCurtains);

        self::assertNull(
            $quoter->quote(Renovation::ThermalCurtains, $quote->resultingHousehold),
            'Already hung: no longer offered.',
        );
    }

    public function testPelletBoilerReplacesTheGenerator(): void
    {
        $quoter = new RenovationQuoter();
        $household = self::barePassoire();
        $quote = $quoter->quote(Renovation::PelletBoiler, $household);

        self::assertNotNull($quote);
        self::assertSame(14000_00, $quote->cost->cents);
        self::assertSame(5600_00, $quote->subsidy->cents, '"Intermédiaire" bracket, 40 %.');
        self::assertSame(HeatingSystem::PelletBoiler, $quote->resultingHousehold->heatingSystem);

        self::assertNull(
            $quoter->quote(Renovation::PelletBoiler, $quote->resultingHousehold),
            'Already the pellet boiler: no longer offered.',
        );
    }

    /**
     * The bridge: once a work has a definition, the quoter must price it from
     * the definition — and still apply the subsidy policy itself, which is the
     * whole reason offers are not quotes.
     */
    public function testPricesAWorkFromItsDefinitionWhenTheCatalogueKnowsIt(): void
    {
        $catalog = new RenovationCatalog([
            new StubDefinition('roof_insulation', Money::fromEuros(1000.0), isEnergyPerformanceWork: true),
        ]);
        $quoter = new RenovationQuoter(catalog: $catalog);

        $quote = $quoter->quote(Renovation::RoofInsulation, self::barePassoire());

        self::assertNotNull($quote);
        self::assertSame('Stub', $quote->title);
        self::assertSame(100_000, $quote->cost->cents);
        self::assertGreaterThan(0, $quote->subsidy->cents, 'the quoter applies the prime, not the definition');
    }

    public function testAppliesNoSubsidyToAWorkOutsideTheAidPerimeter(): void
    {
        $catalog = new RenovationCatalog([
            new StubDefinition('home_battery', Money::fromEuros(1000.0), isEnergyPerformanceWork: false),
        ]);
        $quoter = new RenovationQuoter(catalog: $catalog);

        $quote = $quoter->quote(Renovation::HomeBattery, self::barePassoire());

        self::assertNotNull($quote);
        self::assertSame(0, $quote->subsidy->cents);
    }

    /**
     * A work with no definition yet still goes through the legacy match.
     *
     * Uses {@see Renovation::ThermalCurtains} — still unmigrated at the end of
     * task 4 (envelope drawer). Task 3 used {@see Renovation::RoofInsulation}
     * for this same purpose; it had to move here once roof insulation gained
     * its own definition and its match arm was deleted.
     */
    public function testFallsBackToTheLegacyMatchForUnmigratedWorks(): void
    {
        $quoter = new RenovationQuoter(catalog: new RenovationCatalog([]));

        self::assertNotNull($quoter->quote(Renovation::ThermalCurtains, self::barePassoire()));
    }
}

/** A definition whose offer is fixed, so the quoter's own policy is what gets tested. */
final readonly class StubDefinition implements RenovationDefinition
{
    public function __construct(
        private string $slug,
        private Money $cost,
        private bool $isEnergyPerformanceWork,
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Walls;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        return new RenovationOffer('Stub', $this->cost, $household);
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(AdviceLevel::Info, 'stub');
    }

    public function isEnergyPerformanceWork(): bool
    {
        return $this->isEnergyPerformanceWork;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/battery.svg';
    }
}
