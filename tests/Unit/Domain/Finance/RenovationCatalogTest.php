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
use App\Domain\Energy\EnergyCalibration;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationCatalog;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\SceneSlot;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class RenovationCatalogTest extends TestCase
{
    public function testResolvesAWorkBySlug(): void
    {
        $catalog = new RenovationCatalog([new FakeWork('alpha', SceneSlot::Roof)]);

        self::assertSame('alpha', $catalog->get('alpha')->slug());
    }

    public function testTryGetReturnsNullForAnUnknownSlug(): void
    {
        $catalog = new RenovationCatalog([new FakeWork('alpha', SceneSlot::Roof)]);

        self::assertNull($catalog->tryGet('nope'));
    }

    public function testGetThrowsForAnUnknownSlug(): void
    {
        $catalog = new RenovationCatalog([new FakeWork('alpha', SceneSlot::Roof)]);

        $this->expectException(InvalidArgumentException::class);
        $catalog->get('nope');
    }

    /**
     * A duplicate slug is a programming mistake: two works answering to the
     * same form value would silently shadow each other.
     */
    public function testRejectsDuplicateSlugs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RenovationCatalog([
            new FakeWork('alpha', SceneSlot::Roof),
            new FakeWork('alpha', SceneSlot::Walls),
        ]);
    }

    /**
     * Declaration order IS display order: `worksOfSlot` used to encode it in
     * the template (boiler repair before the heat pump, so a breakdown offers
     * the cheap fix first). The catalogue must not lose it.
     */
    public function testForSlotKeepsDeclarationOrder(): void
    {
        $catalog = new RenovationCatalog([
            new FakeWork('first', SceneSlot::Heating),
            new FakeWork('elsewhere', SceneSlot::Roof),
            new FakeWork('second', SceneSlot::Heating),
        ]);

        $slugs = array_map(
            static fn (RenovationDefinition $w): string => $w->slug(),
            $catalog->forSlot(SceneSlot::Heating),
        );

        self::assertSame(['first', 'second'], $slugs);
    }

    /**
     * The default catalogue, filled up drawer by drawer across tasks 3-5: all
     * fifteen works, in offer/display order — the order `worksOfSlot` used to
     * encode in the template.
     */
    public function testTheDefaultCatalogueListsAllFifteenWorksInOfferOrder(): void
    {
        $slugs = array_map(
            static fn (RenovationDefinition $w): string => $w->slug(),
            new RenovationCatalog()->all(),
        );

        self::assertSame([
            'boiler_repair', 'heat_pump', 'pellet_boiler', 'low_temp_emitters', 'water_heater_thermo',
            'roof_insulation', 'wall_insulation_interior', 'wall_insulation_exterior', 'glazing', 'ventilation_double_flow',
            'solar_panels', 'solar_kit', 'home_battery',
            'draught_proofing', 'thermal_curtains',
        ], $slugs);
    }

    /**
     * The catalogue is now the only source of truth for the works (task 6
     * dropped the `Renovation` enum, whose exhaustive matches used to catch
     * this at the type level) — this count (and the order test above) is what
     * now catches a work whose class exists but was never registered in
     * `defaultWorks()`.
     */
    public function testDefaultCatalogueExposesEveryWorkExactlyOnce(): void
    {
        $catalog = new RenovationCatalog();

        $slugs = array_map(
            static fn (RenovationDefinition $w): string => $w->slug(),
            $catalog->all(),
        );

        self::assertCount(15, $slugs);
        self::assertSame($slugs, array_unique($slugs));
    }

    /**
     * The safety net promised by docs/specs/2026-07-18-catalogue-travaux-design.md
     * §7: dropping the `Renovation` enum (task 6) also dropped PHPStan's
     * exhaustive `match` as a guard against a work's `sceneLayerFor()` naming
     * a layer key the scene does not actually honour — a typo here now fails
     * silently (the layer is simply never rendered) instead of a stan error.
     * This test is that guard, moved to runtime: every non-null value any
     * work can produce, across every state the field it reads can take, must
     * belong to an allow-list independently derived from the scene's own
     * consumers (not from the works themselves — that would be a tautology).
     *
     * The allow-list has two disjoint groups (design-flaw correction, see
     * docs/specs/2026-07-18-catalogue-travaux-design.md §3 sceneLayerFor()):
     *
     * Group A (8 CSS gates): independently derived from scene.css selectors.
     * Each key appears as `.house--{key}` in scene.css with actual styling rules,
     * consumed by HouseShell's class emission. These really bite on typo.
     *
     * Group B (6 cutaway component selectors): partially independently derived
     * and partially a snapshot. Five of them (`heating-heat-pump`, `water-heater-thermo`,
     * `battery`, `solar-full`, `solar-kit`) are independently verified via explicit
     * checks in _cutaway.html.twig (`scene.heatingState == 'heat-pump'`,
     * `scene.waterHeaterThermo`, `scene.garageState == 'installed'`,
     * `scene.solarState == 'full'`, `scene.solarState == 'kit'`). One (`heating-pellet`)
     * has no explicit check in the template — the Boiler component renders it by state
     * but doesn't guard it with a conditional — so it is currently a recorded snapshot
     * of what works produce, pending a follow-up plan to wire these values.
     */
    public function testEveryNonNullSceneLayerBelongsToAConsumerTheSceneActuallyHonours(): void
    {
        // Group A — CSS gates in assets/styles/scene.css, each consumed as
        // `.house--{layer}` by a rule keyed off the classes HouseShell.html.twig
        // emits (e.g., `house--walls-{{ wallInsulation }}`, `house--vmc-{{ ventilation }}`,
        // `house--glazing-{{ glazing }}` for props, or direct classes like
        // `house--roof-ins`, `house--curtains`; see templates/components/scene/HouseShell.html.twig):
        //   .house--roof-ins            (scene.css:140)
        //   .house--walls-interior      (scene.css:141)
        //   .house--walls-exterior      (scene.css:142)
        //   .house--glazing-double      (scene.css:143, 154-157)
        //   .house--glazing-triple      (scene.css:144-145, 158-161)
        //   .house--vmc-double-flow     (scene.css:146)
        //   .house--curtains            (scene.css:147)
        //   .house--floor-heating       (scene.css:148)
        // NB: .house--draughtproofed also exists (scene.css:166) but no work
        // produces it today — DraughtProofingWork::sceneLayerFor() is a
        // deliberate null (design-flaw correction, see the spec §3 above).
        $cssGateLayers = [
            'roof-ins', 'walls-interior', 'walls-exterior',
            'glazing-double', 'glazing-triple',
            'vmc-double-flow', 'curtains', 'floor-heating',
        ];

        // Group B — whole-component selectors in
        // templates/game/scene/_cutaway.html.twig: these gate whether a
        // <twig:scene:*> component renders at all, and have NO .house--*
        // counterpart in scene.css:
        //   scene.heatingState == 'heat-pump'  -> <twig:scene:HeatPump>    (_cutaway.html.twig:162-163)
        //   scene.waterHeaterThermo            -> <twig:scene:WaterHeater> (_cutaway.html.twig:172-173)
        //   scene.garageState == 'installed'   -> <twig:scene:Battery>     (_cutaway.html.twig:185-186)
        //   scene.solarState == 'full'         -> <twig:scene:SolarPanels> on the roof (_cutaway.html.twig:118-119)
        //   scene.solarState == 'kit'          -> <twig:scene:SolarPanels variant="kit"> in the garage (_cutaway.html.twig:193-194)
        // heating-pellet has no dedicated component today (pellet renders via
        // <twig:scene:Boiler state="...">, same as fuel-oil) but is included
        // here as the value HeatPumpWork's sibling PelletBoilerWork produces,
        // matched against scene.heatingState the same way heat-pump is.
        $cutawaySelectorLayers = [
            'heating-heat-pump', 'heating-pellet',
            'water-heater-thermo', 'battery',
            'solar-full', 'solar-kit',
        ];

        $allowList = [...$cssGateLayers, ...$cutawaySelectorLayers];

        $catalog = new RenovationCatalog();
        $energy = new EnergyCalibration();

        foreach ($catalog->all() as $work) {
            foreach (self::reachableHouseholdStates($energy) as $description => $household) {
                $layer = $work->sceneLayerFor($household);
                if (null === $layer) {
                    continue;
                }

                self::assertContains(
                    $layer,
                    $allowList,
                    sprintf(
                        '%s::sceneLayerFor() returned "%s" for state "%s", which no scene consumer honours (not in the CSS-gate or cutaway-selector allow-list).',
                        $work::class,
                        $layer,
                        $description,
                    ),
                );
            }
        }
    }

    /**
     * One-factor-at-a-time household states: every value each field read by
     * some `sceneLayerFor()` can take, varied one at a time from the bare
     * passoire baseline. Sufficient here because no work's `sceneLayerFor()`
     * reads more than one field (verified by inspection of the 15 classes) —
     * a full cross-product would multiply combinations without exercising
     * any branch a single-axis sweep does not already reach.
     *
     * @return iterable<string, Household>
     */
    private static function reachableHouseholdStates(EnergyCalibration $energy): iterable
    {
        $bare = new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(false, WallInsulation::None, Glazing::Single),
            heatingSystem: HeatingSystem::FuelOilBoiler,
        );

        yield 'bare passoire' => $bare;

        yield 'solar: kit power' => $bare->withSolarKwc($energy->solarKitPeakPowerKwc()->value);
        yield 'solar: full install' => $bare->withSolarKwc($energy->defaultSolarPeakPowerKwc()->value);
        yield 'solar: intermediate kit power' => $bare->withSolarKwc(1.5);

        yield 'battery: installed' => $bare->withBatteryKwh($energy->defaultBatteryCapacityKwh()->value);

        yield 'roof: insulated' => $bare->withEnvelope($bare->envelope->withRoofInsulated(true));

        yield 'walls: interior (ITI)' => $bare->withEnvelope($bare->envelope->withWalls(WallInsulation::Interior));
        yield 'walls: exterior (ITE)' => $bare->withEnvelope($bare->envelope->withWalls(WallInsulation::Exterior));

        yield 'glazing: double' => $bare->withEnvelope($bare->envelope->withGlazing(Glazing::Double));
        yield 'glazing: triple' => $bare->withEnvelope($bare->envelope->withGlazing(Glazing::Triple));

        yield 'ventilation: double flow' => $bare->withEnvelope($bare->envelope->withVentilation(Ventilation::DoubleFlow));

        yield 'draught-proofed' => $bare->withEnvelope($bare->envelope->withDraughtProofed(true));
        yield 'thermal curtains' => $bare->withEnvelope($bare->envelope->withThermalCurtains(true));

        yield 'heating: heat pump' => $bare->withHeatingSystem(HeatingSystem::HeatPump);
        yield 'heating: pellet boiler' => $bare->withHeatingSystem(HeatingSystem::PelletBoiler);
        yield 'heating: fuel-oil boiler broken' => $bare->withBoilerBroken(true);

        yield 'low-temp emitters' => $bare->withLowTempEmitters(true);

        yield 'water heater: thermodynamic' => $bare->withWaterHeater(WaterHeater::Thermodynamic);
    }
}

/** A minimal definition, so the catalogue is tested without any real work. */
final readonly class FakeWork implements RenovationDefinition
{
    public function __construct(
        private string $slug,
        private SceneSlot $slot,
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function slot(): SceneSlot
    {
        return $this->slot;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        return null;
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(\App\Domain\Finance\AdviceLevel::Info, 'test');
    }

    public function isEnergyPerformanceWork(): bool
    {
        return false;
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
