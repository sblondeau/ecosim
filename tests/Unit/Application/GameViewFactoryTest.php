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
        self::assertSame('2 100,00 €', $view->monthlyExpensesLabel);
        self::assertSame('700,00 €', $view->monthlyNetIncomeLabel, 'Net = income − living expenses.');
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

    public function testQuotesCarryEstimatedEffects(): void
    {
        $bare = new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler);
        $view = new GameViewFactory()->build(self::config(), GameState::start($bare, Money::fromEuros(4000.0)));

        $solarEffects = implode(' | ', $view->actions['solar_panels']->effectLabels);
        self::assertStringContainsString('kWh/an', $solarEffects, 'Panels announce their annual production.');
        self::assertStringContainsString('Facture énergie : ≈ −', $solarEffects, 'Panels cut the bill.');

        $heatPumpEffects = implode(' | ', $view->actions['heat_pump']->effectLabels);
        self::assertStringContainsString('Facture énergie : ≈ −', $heatPumpEffects, 'Dropping fuel oil saves money every year.');

        self::assertSame(
            [],
            $view->actions['home_battery']->effectLabels,
            'A battery with no panels stores nothing — an honest quote says so.',
        );
    }

    public function testTheSceneModelSpeaksInSemanticStates(): void
    {
        $factory = new GameViewFactory();
        $config = self::config(); // January 15th epoch: winter, fuel burning.

        $bare = new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler);
        $scene = $factory->build($config, GameState::start($bare, Money::fromEuros(4000.0)))->scene;

        self::assertSame('winter', $scene->season);
        self::assertSame('empty', $scene->roofState);
        self::assertSame(0, $scene->insulationTier);
        self::assertSame('fioul', $scene->heatingState);
        self::assertSame('empty', $scene->garageState);
        self::assertTrue($scene->chimneySmoking, 'The boiler burns fuel in January — the chimney shows it.');
        self::assertFalse($scene->producing, 'No panels, no glint.');
        self::assertSame('cool', $scene->comfortState, 'Heated passoire: 16.2 °C felt — chilly, not freezing.');

        $renovated = new Household(3.0, 5.0, InsulationLevel::Reinforced, HeatingSystem::HeatPump);
        $scene = $factory->build($config, GameState::start($renovated, Money::fromEuros(4000.0)))->scene;

        self::assertSame('installed', $scene->roofState);
        self::assertSame(2, $scene->insulationTier);
        self::assertSame('heat-pump', $scene->heatingState);
        self::assertFalse($scene->chimneySmoking, 'A heat pump never smokes.');
        self::assertSame('warm', $scene->comfortState);

        $broken = new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler, boilerBroken: true);
        $scene = $factory->build($config, GameState::start($broken, Money::fromEuros(4000.0)))->scene;

        self::assertSame('fioul-broken', $scene->heatingState);
        self::assertFalse($scene->chimneySmoking, 'A dead boiler burns nothing.');
        self::assertSame('cold', $scene->comfortState, 'Emergency heat only: the occupant is freezing.');
    }

    public function testThermostatExposesBoundsAndLivePreview(): void
    {
        $factory = new GameViewFactory();
        $config = self::config();
        $house = new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler);

        $view = $factory->build($config, GameState::start($house, Money::fromEuros(4000.0)));
        self::assertSame(19, $view->setpointC);
        self::assertTrue($view->setpointCanUp);
        self::assertTrue($view->setpointCanDown);
        self::assertFalse($view->setpointBelowHealthy);
        self::assertStringContainsString('€/an', $view->setpointUpEffectLabel, 'Warming previews a yearly cost.');
        self::assertStringContainsString('+', $view->setpointUpEffectLabel, 'Warmer = more spending.');
        self::assertStringContainsString('−', $view->setpointDownEffectLabel, 'Cooler = savings.');

        // At the 16 °C floor: no more down, and flagged below the health floor.
        $cold = $house->withHeatingSetpointC(16.0);
        $coldView = $factory->build($config, GameState::start($cold, Money::fromEuros(4000.0)));
        self::assertFalse($coldView->setpointCanDown);
        self::assertTrue($coldView->setpointBelowHealthy);
        self::assertSame('', $coldView->setpointDownEffectLabel, 'No preview past the bound.');
    }

    public function testFuelPovertyFlagsThePassoireAndClearsAfterRenovation(): void
    {
        $factory = new GameViewFactory();
        $config = self::config();

        $passoire = new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler);
        $bare = $factory->build($config, GameState::start($passoire, Money::fromEuros(4000.0)));
        self::assertTrue($bare->inFuelPoverty, 'The fuel-oil passoire eats >8% of income.');
        self::assertGreaterThan(8, $bare->energyEffortPct);

        $renovated = new Household(3.0, 5.0, InsulationLevel::Reinforced, HeatingSystem::HeatPump);
        $good = $factory->build($config, GameState::start($renovated, Money::fromEuros(4000.0)));
        self::assertFalse($good->inFuelPoverty, 'Insulation + heat pump + solar clear fuel poverty.');
        self::assertLessThan($bare->energyEffortPct, $good->energyEffortPct);
    }

    public function testHelpTextsQuoteTheCalibratedFigures(): void
    {
        $help = new GameViewFactory()->build(self::config(), GameState::start(self::passoire(), Money::fromEuros(4000.0)))->help;

        self::assertStringContainsString('19 et 26 °C', $help['comfort'], 'The comfort range comes from the registry.');
        self::assertStringContainsString('20 fois moins', $help['surplus'], 'The §8 buy/sell ratio, computed, not hardcoded.');
        self::assertStringContainsString('0,22 €/kWh', $help['electricity']);
        self::assertStringContainsString('CRE', $help['electricity'], 'Sources are named to the player (§13).');
        self::assertStringContainsString('+8 %', $help['propertyValue']);
    }

    public function testWeatherSparklineCoversTheRollingWindow(): void
    {
        $factory = new GameViewFactory();
        $config = self::config();

        $dayOne = $factory->build($config, GameState::start(self::passoire(), Money::fromEuros(4000.0)));
        self::assertSame(1, $dayOne->weatherSparkline->days, 'Day 0: a single point.');

        $day10 = new GameState(9, self::passoire(), 0.0, Money::zero(), Loan::none(), new PeriodTotals());
        self::assertSame(10, $factory->build($config, $day10)->weatherSparkline->days);

        $day100 = new GameState(99, self::passoire(), 0.0, Money::zero(), Loan::none(), new PeriodTotals());
        $spark = $factory->build($config, $day100)->weatherSparkline;
        self::assertSame(30, $spark->days, 'The window never exceeds 30 days.');
        self::assertCount(30, explode(' ', $spark->temperaturePoints));
        self::assertCount(30, explode(' ', $spark->cloudPoints));
    }

    public function testNoEndReportWhileTheGameRuns(): void
    {
        $view = new GameViewFactory()->build(self::config(), GameState::start(self::passoire(), Money::fromEuros(4000.0)));

        self::assertNull($view->endReport);
    }

    public function testEndReportMeasuresEachAxisAgainstDayZero(): void
    {
        $config = new GameConfig(2025, new DateTimeImmutable('2025-01-01'), 3);
        // A renovated home (Retrofitted + heat pump = computed DPE D) with 5 000 €
        // left and an éco-PTZ still running.
        $renovated = new Household(3.0, 0.0, InsulationLevel::Retrofitted, HeatingSystem::HeatPump);
        $atHorizon = new GameState(3, $renovated, 0.0, Money::fromEuros(5000.0), Loan::none()->borrow(Money::fromEuros(24000.0)), new PeriodTotals());

        $report = new GameViewFactory()->build($config, $atHorizon)->endReport;

        self::assertNotNull($report);
        self::assertSame('7 750,00 €', $report->savingsStartLabel, 'The scenario starting savings.');
        self::assertSame('5 000,00 €', $report->savingsEndLabel);
        self::assertSame('−2 750,00 €', $report->savingsDeltaLabel);
        self::assertTrue($report->savingsDeltaNegative);
        self::assertSame('G', $report->dpeStartLetter);
        self::assertSame('D', $report->dpeEndLetter);
        self::assertSame('200 000,00 €', $report->propertyStartLabel);
        self::assertSame('248 000,00 €', $report->propertyEndLabel, '3 DPE classes gained × 8 %.');
        self::assertSame('+48 000,00 €', $report->propertyDeltaLabel);
        self::assertTrue($report->loanActive);
        self::assertSame('24 000,00 €', $report->loanRemainingLabel);
    }
}
