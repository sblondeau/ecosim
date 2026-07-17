<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Scenario\PrimoAccedantScenario;
use App\Twig\Components\GameDashboard;

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
            '<twig:Modal title="Panne" closeAction="acknowledgeBreakdown" closeLabel="OK"><p>corps de la modale</p></twig:Modal>',
        )->render();

        self::assertStringContainsString('corps de la modale', $html, 'The slot body renders.');
        self::assertStringContainsString('acknowledgeBreakdown', $html, 'The close button triggers the action.');
    }

    public function testWelcomeOverlayShowsOnAFreshGameThenDismisses(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // A brand-new game (day 1) greets the player with the welcome overlay.
        self::assertStringContainsString('Bienvenue chez vous', (string) $component->render());

        $html = (string) $component->call('dismissIntro')->render();

        self::assertTrue($component->component()->introDismissed);
        self::assertStringNotContainsString('intro-overlay', $html);
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

        self::assertTrue($component->component()->noticeIsError);
        self::assertStringContainsString('éco-PTZ', $component->component()->notice);
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

    public function testSuccessfulRenovationInstallsAndNotifies(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // Solar panels cost 7 500 € — affordable in cash with the 7 750 € savings.
        $html = (string) $component->call('order', ['work' => 'solar_panels', 'financing' => 'cash'])->render();

        self::assertFalse($component->component()->noticeIsError);
        self::assertStringContainsString('réalisés', $component->component()->notice);
        self::assertTrue(str_contains($html, 'class="solar"'), 'The panels now render on the roof.');
    }
}
