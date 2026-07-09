<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\GameViewFactory;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
use App\Domain\Finance\Loan;
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

    public function testMonthlyBudgetShowsIncomeExpensesAndNet(): void
    {
        $view = new GameViewFactory()->build(self::config(), GameState::start(self::passoire(), Money::fromEuros(8000.0)));

        self::assertSame('2 800,00 €', $view->monthlyIncomeLabel);
        self::assertSame('2 300,00 €', $view->monthlyExpensesLabel);
        self::assertSame('500,00 €', $view->monthlyNetIncomeLabel, 'Net = income − living expenses.');
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
        $atHorizon = new GameState(3, self::passoire(), 0.0, Money::zero(), Loan::none(), new PeriodTotals());

        self::assertTrue(new GameViewFactory()->build($config, $atHorizon)->finished);
    }

    public function testNoEndReportWhileTheGameRuns(): void
    {
        $view = new GameViewFactory()->build(self::config(), GameState::start(self::passoire(), Money::fromEuros(4000.0)));

        self::assertNull($view->endReport);
    }

    public function testEndReportMeasuresEachAxisAgainstDayZero(): void
    {
        $config = new GameConfig(2025, new DateTimeImmutable('2025-01-01'), 3);
        // A renovated home (Retrofitted + heat pump = DPE C) with 5 000 € left
        // and an éco-PTZ still running.
        $renovated = new Household(3.0, 0.0, InsulationLevel::Retrofitted, HeatingSystem::HeatPump);
        $atHorizon = new GameState(3, $renovated, 0.0, Money::fromEuros(5000.0), Loan::none()->borrow(Money::fromEuros(24000.0)), new PeriodTotals());

        $report = new GameViewFactory()->build($config, $atHorizon)->endReport;

        self::assertNotNull($report);
        self::assertSame('4 000,00 €', $report->savingsStartLabel, 'The scenario starting savings.');
        self::assertSame('5 000,00 €', $report->savingsEndLabel);
        self::assertSame('+1 000,00 €', $report->savingsDeltaLabel);
        self::assertFalse($report->savingsDeltaNegative);
        self::assertSame('G', $report->dpeStartLetter);
        self::assertSame('C', $report->dpeEndLetter);
        self::assertSame('200 000,00 €', $report->propertyStartLabel);
        self::assertSame('264 000,00 €', $report->propertyEndLabel, '4 DPE classes gained × 8 %.');
        self::assertSame('+64 000,00 €', $report->propertyDeltaLabel);
        self::assertTrue($report->loanActive);
        self::assertSame('24 000,00 €', $report->loanRemainingLabel);
    }
}
