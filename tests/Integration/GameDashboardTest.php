<?php

declare(strict_types=1);

namespace App\Tests\Integration;

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

    public function testWelcomeOverlayShowsOnAFreshGameThenDismisses(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        // A brand-new game (day 1) greets the player with the welcome overlay.
        self::assertStringContainsString('Bienvenue chez vous', (string) $component->render());

        $html = (string) $component->call('dismissIntro')->render();

        self::assertTrue($component->component()->introDismissed);
        self::assertStringNotContainsString('intro-overlay', $html);
    }

    public function testMenuPanelRendersTheOfficialDualDpeLabel(): void
    {
        $component = $this->createLiveComponent(GameDashboard::class);

        $html = (string) $component->call('selectSlot', ['slot' => 'menu'])->render();

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
