<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\Renovation;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationAdvisor;
use App\Domain\Finance\RenovationCatalog;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\SceneSlot;
use LogicException;
use PHPUnit\Framework\TestCase;

final class RenovationAdvisorTest extends TestCase
{
    private RenovationAdvisor $advisor;

    protected function setUp(): void
    {
        $this->advisor = new RenovationAdvisor();
    }

    private function house(EnvelopeState $envelope, HeatingSystem $heating = HeatingSystem::FuelOilBoiler, bool $broken = false): Household
    {
        return new Household(0.0, 0.0, $envelope, $heating, $broken);
    }

    private function bare(): EnvelopeState
    {
        return new EnvelopeState(false, WallInsulation::None, Glazing::Single);
    }

    public function testRoofInsulationIsAlwaysAneutralRepere(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::RoofInsulation, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertStringContainsString('gain/prix', $advice->message);
    }

    public function testHeatPumpCautionedInAPoorlyInsulatedHouse(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::HeatPump, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Caution, $advice->level);
        self::assertStringContainsString('surdimensionnée', $advice->message);
    }

    public function testHeatPumpIsInfoOnceInsulated(): void
    {
        $insulated = new EnvelopeState(true, WallInsulation::Interior, Glazing::Single); // f = 0.60
        $advice = $this->advisor->adviceFor(Renovation::HeatPump, $this->house($insulated));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
    }

    public function testHeatPumpDuringBreakdownIsInfoNotCaution(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::HeatPump, $this->house($this->bare(), broken: true));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertStringContainsString('fioul', $advice->message);
    }

    public function testGlazingCautionedWhileEnvelopeUntreated(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::Glazing, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Caution, $advice->level);
    }

    public function testGlazingIsInfoOnceEnvelopeTreated(): void
    {
        $insulated = new EnvelopeState(true, WallInsulation::Interior, Glazing::Single); // f = 0.60
        $advice = $this->advisor->adviceFor(Renovation::Glazing, $this->house($insulated));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
    }

    public function testWallOptionsDescribeTheirTradeoff(): void
    {
        $iti = $this->advisor->adviceFor(Renovation::WallInsulationInterior, $this->house($this->bare()));
        $ite = $this->advisor->adviceFor(Renovation::WallInsulationExterior, $this->house($this->bare()));

        self::assertNotNull($iti);
        self::assertNotNull($ite);
        self::assertStringContainsString('surface habitable', $iti->message);
        self::assertStringContainsString('façade', $ite->message);
    }

    public function testLowTempEmittersHighlightTheScopGainWithAHeatPump(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::LowTempEmitters, $this->house($this->bare(), HeatingSystem::HeatPump));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertStringContainsString('SCOP', $advice->message);
    }

    public function testLowTempEmittersAreOfLimitedUseWithoutAHeatPump(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::LowTempEmitters, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertStringContainsString('pompe à chaleur', $advice->message);
    }

    public function testVentilationDoubleFlowCautionedInAPoorlyInsulatedHouse(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::VentilationDoubleFlow, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Caution, $advice->level);
        self::assertStringContainsString('APRÈS l\'isolation', $advice->message);
    }

    public function testVentilationDoubleFlowIsInfoOnceInsulated(): void
    {
        $insulated = new EnvelopeState(true, WallInsulation::Interior, Glazing::Single); // f = 0.60
        $advice = $this->advisor->adviceFor(Renovation::VentilationDoubleFlow, $this->house($insulated));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertStringContainsString('Récupère la chaleur', $advice->message);
    }

    public function testSolarKitAdvisesTheAccessibleFirstStep(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::SolarKit, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Le premier pas accessible : sans installateur ni aide, rendement modeste.', $advice->message);
    }

    public function testPelletBoilerAdvisesLowCarbonButManualHandling(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::PelletBoiler, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertStringContainsString('silo', $advice->message);
    }

    public function testWaterHeaterThermoAdvisesTheOftenOverlookedShare(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::WaterHeaterThermo, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('L\'eau chaude = ~15 % de l\'énergie, souvent oubliée : le thermodynamique divise sa conso par ~3.', $advice->message);
    }

    public function testDraughtProofingAdviceIsHonestAboutTheSmallLever(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::DraughtProofing, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Geste bon marché : coupe les courants d\'air (quelques % de pertes). Utile en complément — pas un gros levier.', $advice->message);
    }

    public function testThermalCurtainsAdviceIsHonestAboutTheSmallLever(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::ThermalCurtains, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertSame('Geste bon marché : un peu de confort près des fenêtres la nuit. Petit levier, pas un substitut à l\'isolation.', $advice->message);
    }

    /**
     * Bridge test: when a work has a definition in the catalog, the advisor must
     * return that definition's advice (not the legacy match). This locks down that
     * the new bridge branch is exercised and that it takes precedence.
     */
    public function testReturnsDefinitionAdviceWhenCatalogKnowsTheWork(): void
    {
        $definition = new AdvisorTestDefinition('roof_insulation', 'definition advice');
        $catalog = new RenovationCatalog([$definition]);
        $advisor = new RenovationAdvisor(catalog: $catalog);
        $household = $this->house($this->bare());

        $advice = $advisor->adviceFor(Renovation::RoofInsulation, $household);

        self::assertSame('definition advice', $advice->message);
    }

    /**
     * Every work now has a definition, so the legacy match is dead code kept
     * only as a defensive safety net (its own comment: reaching it would mean
     * `defaultWorks()` lost an entry — a real bug). This used to be the
     * "falls back to the legacy match" bridge test, repointed at a different
     * unmigrated work at the end of each of tasks 3 and 4; task 5 leaves none
     * to repoint it to, so it is repurposed to lock down the safety net
     * itself — an artificially-empty catalogue is what still exercises it.
     */
    public function testThrowsWhenTheCatalogDoesNotKnowTheWork(): void
    {
        $advisor = new RenovationAdvisor(catalog: new RenovationCatalog([]));
        $household = $this->house($this->bare());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"thermal_curtains" is migrated to the renovation catalogue — the bridge above should have answered it.');

        $advisor->adviceFor(Renovation::ThermalCurtains, $household);
    }
}

/**
 * A definition whose advice is fixed, so the advisor's catalog bridge is what
 * gets tested — not the legacy match arm.
 */
final readonly class AdvisorTestDefinition implements RenovationDefinition
{
    public function __construct(
        private string $slug,
        private string $adviceMessage,
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
        return null;
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(AdviceLevel::Info, $this->adviceMessage);
    }

    public function isEnergyPerformanceWork(): bool
    {
        return true;
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
