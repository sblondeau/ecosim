<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Scenario\PrimoAccedantScenario;
use App\Twig\Components\GameDashboard;
use App\Twig\Components\NoticeSeverity;

use function str_contains;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * End-to-end proof that the dashboard's LiveActions mutate the persisted game
 * (they replaced the controller's POST routes). Game-state changes are checked
 * on the rendered HTML (rendered inside a request, so the session-backed store
 * is available); transient feedback is checked on the LiveProps.
 */
final class GameDashboardTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    public function testAdjustSetpointMovesTheWallThermostat(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // Fresh game starts at 19 °C; one nudge up lands on 20.
        $html = (string) $component->call('adjustSetpoint', ['delta' => 1])->render();

        self::assertTrue(str_contains($html, '20°'), 'The wall thermostat now reads 20°.');
        self::assertFalse(str_contains($html, '19°'), 'It no longer reads 19°.');
    }

    public function testSetSpeedActivatesTheChosenButton(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        $html = (string) $component->call('setSpeed', ['speed' => 2])->render();

        // The ×2 button carries the active class (title is its unique marker).
        self::assertMatchesRegularExpression('/active[^>]*1 jour \/ 6 s/', $html);
    }

    public function testSelectingASlotOpensItAndClickingAgainCloses(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        $component->call('selectSlot', ['slot' => 'heating']);
        self::assertSame('heating', $component->component()->selectedSlot);

        $component->call('selectSlot', ['slot' => 'heating']);
        self::assertNull($component->component()->selectedSlot);
    }

    public function testModalComponentRendersItsSlotAndCloseAction(): void
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(\Twig\Environment::class, $twig);

        $html = $twig->createTemplate(
            '<twig:Modal title="Panne" closeAction="acknowledgeEvent" closeLabel="OK"><p>corps de la modale</p></twig:Modal>',
        )->render();

        self::assertStringContainsString('corps de la modale', $html, 'The slot body renders.');
        self::assertStringContainsString('acknowledgeEvent', $html, 'The close button triggers the action.');
    }

    public function testOnboardingChainsTheImmersiveIntroThenTheBriefingThenReleasesTheGame(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // A brand-new game (day 1) greets the player with the immersive intro —
        // pure situation, and it names the starting DPE correctly (G, not F).
        $intro = (string) $component->render();
        self::assertStringContainsString('Bienvenue chez vous', $intro);
        self::assertStringNotContainsString('DPE&nbsp;F', $intro, 'The starting home is DPE G — the old copy mislabelled it F.');

        // Acknowledging the intro reveals the briefing (axes + how-to), NOT the game.
        $briefing = (string) $component->call('acknowledgeEvent', ['id' => 'intro'])->render();
        self::assertSame(['intro'], $component->component()->acknowledgedEvents);
        self::assertStringContainsString('Énergie &amp; climat', $briefing, 'The briefing lists the fourth axis the old intro omitted.');
        self::assertStringContainsString('data-live-id-param="briefing"', $briefing, 'The briefing is what the intro dismisses to.');

        // Acknowledging the briefing dismisses the last modal and starts play.
        $game = (string) $component->call('acknowledgeEvent', ['id' => 'briefing'])->render();
        self::assertStringNotContainsString('intro-overlay', $game);
    }

    public function testAcknowledgingEachOnboardingModalRevealsTheNextPendingEventImmediately(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // Fast-forward straight to the boiler breakdown morning.
        for ($day = 0; $day < 20; ++$day) {
            $component->call('step');
        }

        // Intro, briefing and the breakdown have all occurred; the intro shows first.
        self::assertStringContainsString('Bienvenue chez vous', (string) $component->render());

        // Closing the intro reveals the briefing, same render.
        $briefing = (string) $component->call('acknowledgeEvent', ['id' => 'intro'])->render();
        self::assertStringContainsString('data-live-id-param="briefing"', $briefing);

        // Closing the briefing reveals the breakdown modal, same render.
        $breakdown = (string) $component->call('acknowledgeEvent', ['id' => 'briefing'])->render();
        self::assertStringContainsString('Panne de chaudière', $breakdown);

        // The close button's LiveArg wire name must be lowercase — a camelCase
        // data-live-*-param gets folded to lowercase by the HTML parser before
        // Stimulus ever reads it, silently breaking argument resolution.
        self::assertStringContainsString('data-live-id-param="boiler_breakdown"', $breakdown);
    }

    public function testPatrimoineCornerRendersTheOfficialDualDpeLabel(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        $html = (string) $component->call('selectSlot', ['slot' => 'patrimoine'])->render();

        // Both official scales are drawn, each with exactly one highlighted class
        // (the letter itself depends on the house, so it is not pinned here).
        self::assertStringContainsString('Consommation', $html);
        self::assertStringContainsString('kgCO₂/m²·an', $html);
        self::assertMatchesRegularExpression('/dpe-e-[a-g] is-active/', $html);
        self::assertMatchesRegularExpression('/dpe-g-[a-g] is-active/', $html);
    }

    public function testRefusedRenovationSurfacesAnErrorNoticeInsteadOfAFlash(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // Solar panels are not éco-PTZ eligible (energy-performance works only) — refused.
        $component->call('order', ['work' => 'solar_panels', 'financing' => 'loan']);

        self::assertSame(NoticeSeverity::Error, $component->component()->notice->severity);
        self::assertStringContainsString('éco-PTZ', $component->component()->notice->text);
    }

    public function testSelectingWallsSlotOffersTheFourEnvelopeWorks(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // A brand-new game starts fully uninsulated: all four surface works are quoted.
        $html = (string) $component->call('selectSlot', ['slot' => 'walls'])->render();

        self::assertStringContainsString('Isolation des combles', $html);
        self::assertStringContainsString('intérieure (ITI)', $html);
        self::assertStringContainsString('extérieure (ITE)', $html);
        self::assertStringContainsString('Menuiseries', $html);
    }

    public function testWallsSlotSurfacesTheRoofInsulationAdvice(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // A brand-new game is fully uninsulated: the roof-insulation quote
        // carries its non-prescriptive "best value" advice (💡 badge).
        $html = (string) $component->call('selectSlot', ['slot' => 'walls'])->render();

        self::assertStringContainsString('gain/prix', $html, 'The roof-insulation advice renders in the walls drawer.');
    }

    public function testHeatingSlotCautionsAgainstAnOversizedHeatPump(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // The house starts poorly insulated (no boiler breakdown yet), so the
        // heat-pump quote carries the ⚠️ oversizing caution.
        $html = (string) $component->call('selectSlot', ['slot' => 'heating'])->render();

        self::assertStringContainsString('surdimensionnée', $html, 'The heat-pump caution renders in the heating drawer.');
    }

    public function testHeatingSlotOffersThePelletBoilerAndLowTempEmitters(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // A brand-new game is still on the fuel-oil boiler with no emitters
        // upgrade yet: both alternative-generator quotes are on offer.
        $html = (string) $component->call('selectSlot', ['slot' => 'heating'])->render();

        self::assertStringContainsString('Chaudière à granulés', $html);
        self::assertStringContainsString('Émetteurs basse température', $html);
    }

    public function testLowTempEmittersCardSurfacesTheScopAdviceOnceAHeatPumpIsInstalled(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // Éco-PTZ covers the heat pump within its cap — no cash needed.
        $component->call('order', ['work' => 'heat_pump', 'financing' => 'loan']);
        $html = (string) $component->call('selectSlot', ['slot' => 'heating'])->render();

        self::assertStringContainsString('SCOP', $html, 'The low-temp-emitters advice quotes the heat pump\'s SCOP once a heat pump is installed.');
    }

    public function testHeatingSlotShowsTheBreakdownIndicatorOnceTheBoilerBreaksDown(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // `step` lives exactly one game day per call, wall-clock independent
        // (TimeKeeper::step()) — BOILER_BREAKDOWN_DAY manual steps land right
        // on the scripted breakdown morning (day 19 / January 20th).
        for ($day = 0; $day < PrimoAccedantScenario::BOILER_BREAKDOWN_DAY; ++$day) {
            $component->call('step');
        }

        $html = (string) $component->call('selectSlot', ['slot' => 'heating'])->render();

        self::assertStringContainsString('En panne', $html, 'The heating slot flags the breakdown instead of the stale "Chaudière fioul" header.');
        self::assertStringContainsString("chauffage électrique d'appoint forcé", $html);
    }

    public function testGarageSlotOffersTheSolarKit(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // The plug-and-play kit stands on the ground, not on the roof, so it is
        // decided alongside the battery — same subject: producing and storing
        // your own electricity without a roofing job. A brand-new game has no
        // solar at all, so the cheap kit is quoted.
        $html = (string) $component->call('selectSlot', ['slot' => 'garage'])->render();

        self::assertStringContainsString('Kit solaire', $html);

        $roof = (string) $component->call('selectSlot', ['slot' => 'roof'])->render();

        self::assertStringNotContainsString('Kit solaire', $roof);
    }

    public function testHeatingSlotOffersTheThermodynamicWaterHeater(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // The tank lives with the boiler, not in the garage: same plant room,
        // same pipework. A brand-new game starts on the baseline electric tank,
        // so the upgrade is quoted.
        $html = (string) $component->call('selectSlot', ['slot' => 'heating'])->render();

        self::assertStringContainsString('Chauffe-eau thermodynamique', $html);

        $garage = (string) $component->call('selectSlot', ['slot' => 'garage'])->render();

        self::assertStringNotContainsString('Chauffe-eau thermodynamique', $garage);
    }

    /**
     * Task 4 (arbre travaux, palier 5): the drawers' "done" chips are now
     * fully catalogue-driven, grouped strictly by RenovationDefinition::slot()
     * instead of the template's old, independently-placed flags. This moves
     * three chips relative to the pre-Task-4 rendering — approved changes,
     * proven here rather than silently relied upon.
     */
    public function testGarageDrawerShowsTheSolarKitDoneChipOnceInstalled(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // The 800 € plug-and-play kit is affordable in cash from the 7 750 €
        // starting savings.
        $component->call('order', ['work' => 'solar_kit', 'financing' => 'cash']);

        $garage = (string) $component->call('selectSlot', ['slot' => 'garage'])->render();
        self::assertStringContainsString(
            'done-chip">✔ Kit solaire',
            $garage,
            'SolarKitWork::slot() is Garage: the done chip now lives where the offer already did, not in roof (Task 4, approved change 2/3).',
        );

        $roof = (string) $component->call('selectSlot', ['slot' => 'roof'])->render();
        self::assertStringNotContainsString(
            'done-chip">✔ Kit solaire',
            $roof,
            'The roof drawer no longer shows the done chip: SolarKitWork::slot() is Garage.',
        );
    }

    /**
     * Follow-up to Task 4: the roof drawer's "Installation actuelle" row must
     * name the ROOFTOP install only. With a kit-only household, it must read
     * "Aucune" — the kit is decided/shown in the garage drawer instead.
     */
    public function testRoofDrawerContextRowReadsNoneWithOnlyTheGroundKitInstalled(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        $component->call('order', ['work' => 'solar_kit', 'financing' => 'cash']);

        $roof = (string) $component->call('selectSlot', ['slot' => 'roof'])->render();
        self::assertMatchesRegularExpression(
            '/Installation actuelle<\/span><span><strong>Aucune<\/strong>/',
            $roof,
            'A kit-only household has no rooftop install: the roof drawer\'s context row reads "Aucune", not the kit label.',
        );
    }

    /**
     * Mirror of the above: the garage drawer's "Équipement actuel" row must
     * name the kit once installed (its home now that SolarKitWork::slot() is
     * Garage), and must never name the water heater (moved to the heating
     * drawer in the same refactor).
     */
    public function testGarageDrawerContextRowNamesTheKitNotTheWaterHeater(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        $component->call('order', ['work' => 'solar_kit', 'financing' => 'cash']);
        $component->call('order', ['work' => 'water_heater_thermo', 'financing' => 'cash']);

        $garage = (string) $component->call('selectSlot', ['slot' => 'garage'])->render();

        // Scoped to the "Équipement actuel" row's value span itself — the
        // scene SVG always draws the installed water-heater asset (whose
        // markup carries its own "Chauffe-eau thermodynamique" comment)
        // regardless of which drawer is open, so a page-wide assertion would
        // false-positive on that unrelated graphic.
        $matched = preg_match(
            '/Équipement actuel<\/span>\s*<span>(.*?)<\/span>\s*<\/div>/s',
            $garage,
            $row,
        );
        self::assertSame(1, $matched, 'The garage drawer renders its "Équipement actuel" context row.');
        self::assertStringContainsString(
            'Kit solaire',
            $row[1],
            'The garage drawer\'s context row names the kit alongside the battery state.',
        );
        self::assertStringNotContainsString(
            'Chauffe-eau',
            $row[1],
            'The water heater is never named in the garage drawer\'s context row any more: it moved to the heating drawer.',
        );
    }

    public function testHeatingDrawerShowsTheThermodynamicWaterHeaterDoneChipOnceInstalled(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // 3 500 €, affordable in cash from the 7 750 € starting savings.
        $component->call('order', ['work' => 'water_heater_thermo', 'financing' => 'cash']);

        $heating = (string) $component->call('selectSlot', ['slot' => 'heating'])->render();
        self::assertStringContainsString(
            'done-chip">✔ Chauffe-eau thermodynamique',
            $heating,
            'WaterHeaterThermoWork::slot() is Heating: the done chip now lives where the offer already did, not in garage (Task 4, approved change 1/3).',
        );

        $garage = (string) $component->call('selectSlot', ['slot' => 'garage'])->render();
        self::assertStringNotContainsString(
            'done-chip">✔ Chauffe-eau thermodynamique',
            $garage,
            'The garage drawer no longer shows the done chip, nor names the water heater in its context row any more (Task 4 + follow-up fix).',
        );
    }

    public function testHeatingDrawerNoLongerBadgesTheStartingFuelOilBoilerAsDone(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // A brand-new game starts on fuel oil: no catalogue work claims
        // "still on the starting boiler" as a done upgrade (only switching TO
        // a heat pump/pellet boiler does).
        $html = (string) $component->call('selectSlot', ['slot' => 'heating'])->render();

        self::assertStringContainsString('Chaudière fioul', $html, 'The "Générateur actuel" context row still names it.');
        self::assertStringNotContainsString(
            'done-chip">✔ Chaudière fioul',
            $html,
            'But the starting boiler is no longer badged as a "done" chip (Task 4, approved change 3/3).',
        );
    }

    public function testLivingSlotOffersTheDraughtProofingAndThermalCurtainsGestures(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // A brand-new game starts with neither daily gesture done: both are quoted.
        $html = (string) $component->call('selectSlot', ['slot' => 'living'])->render();

        self::assertStringContainsString('Calfeutrage', $html);
        self::assertStringContainsString('Rideaux thermiques', $html);
    }

    public function testWallsSlotOffersTheDoubleFlowVentilation(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // A brand-new game has no ventilation upgrade yet: the VMC is quoted.
        $html = (string) $component->call('selectSlot', ['slot' => 'walls'])->render();

        self::assertStringContainsString('VMC double flux', $html);
    }

    public function testSuccessfulRenovationInstallsAndNotifies(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // Solar panels cost 7 500 € — affordable in cash with the 7 750 € savings.
        $html = (string) $component->call('order', ['work' => 'solar_panels', 'financing' => 'cash'])->render();

        self::assertSame(NoticeSeverity::Success, $component->component()->notice->severity);
        self::assertStringContainsString('réalisés', $component->component()->notice->text);
        self::assertTrue(str_contains($html, 'class="solar solar--full"'), 'The full roof array now renders — not the ground-mounted kit.');
    }
}
