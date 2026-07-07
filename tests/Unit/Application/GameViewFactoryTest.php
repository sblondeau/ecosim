<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\GameViewFactory;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
use App\Domain\Finance\Money;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\PeriodTotals;
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

    private static function passoire(): Household
    {
        return new Household(3.0, 5.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler);
    }

    public function testBuildsDisplayReadyScalars(): void
    {
        $view = new GameViewFactory()->build(self::config(), GameState::start(self::passoire(), Money::fromEuros(8000.0)));

        self::assertSame(1, $view->dayNumber);
        self::assertSame('Hiver', $view->seasonLabel);
        self::assertStringContainsString('janvier 2025', $view->dateLabel);
        self::assertSame(3.0, $view->solarKwc);
        self::assertSame(5.0, $view->batteryCapacityKwh);
        self::assertSame('Chaudière fioul', $view->heatingLabel);
        self::assertSame('D\'origine', $view->insulationLabel);
        self::assertSame('G', $view->dpeLetter);
        self::assertGreaterThan(0.0, $view->fuelOilLitres, 'A January day in the passoire burns fuel oil.');
        self::assertFalse($view->finished);
    }

    public function testPercentagesStayWithinBounds(): void
    {
        $view = new GameViewFactory()->build(self::config(), GameState::start(self::passoire(), Money::fromEuros(8000.0)));

        self::assertGreaterThanOrEqual(0, $view->cloudPct);
        self::assertLessThanOrEqual(100, $view->cloudPct);
        self::assertGreaterThanOrEqual(0, $view->selfSufficiencyPct);
        self::assertLessThanOrEqual(100, $view->selfSufficiencyPct);
        self::assertGreaterThanOrEqual(0, $view->comfortScorePct);
        self::assertLessThanOrEqual(100, $view->comfortScorePct);
    }

    public function testReportsFinishedAtHorizon(): void
    {
        $config = new GameConfig(2025, new DateTimeImmutable('2025-01-01'), 3);
        $atHorizon = new GameState(3, self::passoire(), 0.0, Money::zero(), new PeriodTotals());

        self::assertTrue(new GameViewFactory()->build($config, $atHorizon)->finished);
    }
}
