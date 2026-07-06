<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\GameViewFactory;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GameViewFactoryTest extends TestCase
{
    private static function config(): GameConfig
    {
        return new GameConfig(
            seed: 2025,
            epoch: new DateTimeImmutable('2025-01-15'),
            horizonDays: 365,
        );
    }

    private static function equippedState(): GameState
    {
        return GameState::start(solarKwc: 3.0, batteryKwh: 5.0);
    }

    public function testBuildsDisplayReadyScalars(): void
    {
        $view = new GameViewFactory()->build(self::config(), self::equippedState());

        self::assertSame(1, $view->dayNumber);
        self::assertSame('Hiver', $view->seasonLabel);
        self::assertStringContainsString('janvier 2025', $view->dateLabel);
        self::assertSame(3.0, $view->solarKwc);
        self::assertSame(5.0, $view->batteryCapacityKwh);
        self::assertFalse($view->finished);
    }

    public function testPercentagesStayWithinBounds(): void
    {
        $view = new GameViewFactory()->build(self::config(), self::equippedState());

        self::assertGreaterThanOrEqual(0, $view->cloudPct);
        self::assertLessThanOrEqual(100, $view->cloudPct);
        self::assertGreaterThanOrEqual(0, $view->selfSufficiencyPct);
        self::assertLessThanOrEqual(100, $view->selfSufficiencyPct);
    }

    public function testReportsFinishedAtHorizon(): void
    {
        $config = new GameConfig(2025, new DateTimeImmutable('2025-01-01'), 3);
        $atHorizon = new GameState(3, 3.0, 5.0, 0.0, new \App\Domain\Simulation\PeriodTotals());

        self::assertTrue(new GameViewFactory()->build($config, $atHorizon)->finished);
    }
}
