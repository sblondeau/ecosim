<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\GameViewFactory;
use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\Ventilation;
use App\Domain\Building\WallInsulation;
use App\Domain\Finance\Money;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Regression guard for the envelope-visual refactor (arbre travaux, per-surface
 * scene model — Tranche 7 catalogue-driven view layer): HouseShell's SOURCE
 * moves from seven per-surface props to {@see \App\Application\HouseSceneView::$envelopeLayers}
 * (catalogue-sourced, `HouseShell` loops over it), but the SET of `house--*`
 * CSS gates it emits for a fully renovated house must stay exactly the same.
 * Order is irrelevant — CSS class matching does not care about attribute
 * order — only presence/absence of each gate is checked here.
 */
final class HouseShellEnvelopeRenderTest extends KernelTestCase
{
    public function testAFullyRenovatedHouseholdEmitsAllSevenEnvelopeGates(): void
    {
        $envelope = new EnvelopeState(
            roofInsulated: true,
            walls: WallInsulation::Exterior,
            glazing: Glazing::Triple,
            ventilation: Ventilation::DoubleFlow,
            draughtProofed: true,
            thermalCurtains: true,
        );
        $household = new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: $envelope,
            heatingSystem: HeatingSystem::HeatPump,
            lowTempEmitters: true,
        );

        $config = new GameConfig(seed: 2025, epoch: new DateTimeImmutable('2025-01-15'), horizonDays: 365);
        $view = new GameViewFactory()->build($config, GameState::start($household, Money::fromEuros(8000.0)));

        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $html = $twig->render('game/scene/_cutaway.html.twig', ['game' => $view, 'selected' => null]);

        foreach ([
            'house--roof-ins',
            'house--walls-exterior',
            'house--glazing-triple',
            'house--vmc-double-flow',
            'house--curtains',
            'house--draughtproofed',
            'house--floor-heating',
        ] as $expectedGate) {
            self::assertStringContainsString($expectedGate, $html, "Expected the {$expectedGate} gate on a fully renovated house.");
        }
    }
}
