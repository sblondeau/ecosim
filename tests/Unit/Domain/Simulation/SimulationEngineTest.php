<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Simulation;

use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
use App\Domain\Finance\Loan;
use App\Domain\Finance\Money;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\PeriodTotals;
use App\Domain\Simulation\SimulationEngine;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SimulationEngineTest extends TestCase
{
    private static function config(int $horizonDays = 5): GameConfig
    {
        return new GameConfig(
            seed: 2025,
            epoch: new DateTimeImmutable('2025-01-01'),
            horizonDays: $horizonDays,
        );
    }

    private static function passoire(): Household
    {
        return new Household(3.0, 5.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler);
    }

    public function testSnapshotIsDeterministic(): void
    {
        $engine = new SimulationEngine();
        $config = self::config();
        $state = GameState::start(self::passoire(), Money::fromEuros(8000.0));

        $a = $engine->snapshot($config, $state);
        $b = $engine->snapshot($config, $state);

        self::assertSame($a->balance->productionKwh, $b->balance->productionKwh);
        self::assertSame($a->weather->temperatureC, $b->weather->temperatureC);
        self::assertSame($a->heating->fuelOilLitres, $b->heating->fuelOilLitres);
        self::assertSame($a->comfort->score, $b->comfort->score);
    }

    public function testAdvanceMovesToNextDayAndFoldsTheBalance(): void
    {
        $engine = new SimulationEngine();
        $config = self::config();
        $state = GameState::start(self::passoire(), Money::fromEuros(8000.0));

        $snapshot = $engine->snapshot($config, $state);
        $next = $engine->advance($config, $state);

        self::assertSame(1, $next->currentDay);
        self::assertSame($snapshot->balance->batteryLevelKwh, $next->batteryLevelKwh);
        self::assertSame($snapshot->balance->productionKwh, $next->totals->productionKwh);
        self::assertSame($snapshot->balance->gridImportKwh, $next->totals->importKwh);
        self::assertSame($snapshot->heating->fuelOilLitres, $next->totals->fuelOilLitres);
        self::assertSame((float) $snapshot->comfort->score, $next->totals->comfortScoreSum);
    }

    public function testAdvanceCarriesTheHouseholdForward(): void
    {
        $engine = new SimulationEngine();
        $state = GameState::start(self::passoire(), Money::fromEuros(8000.0));

        $next = $engine->advance(self::config(), $state);

        self::assertSame($state->household, $next->household);
    }

    public function testIncomeLandsOnTheFirstOfTheMonthAndBillsArePaidDaily(): void
    {
        $engine = new SimulationEngine();
        $config = self::config();
        $state = GameState::start(self::passoire(), Money::fromEuros(8000.0));

        // Day 0 is January 1st: net income credited, day's bill paid.
        $jan1 = $engine->snapshot($config, $state);
        self::assertGreaterThan(0, $jan1->incomeCredited->cents, 'The 1st of the month credits the net income.');
        self::assertGreaterThan(0, $jan1->bill->fuelOilCost->cents, 'A January day in the passoire has a fioul bill.');

        $afterJan1 = $engine->advance($config, $state);
        $expected = 800000 + $jan1->incomeCredited->cents - $jan1->bill->netCost()->cents;
        self::assertSame($expected, $afterJan1->savings->cents);

        // Day 1 is January 2nd: no income, bill still paid.
        $jan2 = $engine->snapshot($config, $afterJan1);
        self::assertSame(0, $jan2->incomeCredited->cents);
        $afterJan2 = $engine->advance($config, $afterJan1);
        self::assertSame(
            $afterJan1->savings->cents - $jan2->bill->netCost()->cents,
            $afterJan2->savings->cents,
        );
    }

    public function testLoanInstallmentIsPaidOnTheFirstOfTheMonth(): void
    {
        $engine = new SimulationEngine();
        $config = self::config();
        $state = GameState::start(self::passoire(), Money::fromEuros(8000.0))
            ->renovated(self::passoire(), Money::fromEuros(8000.0), Loan::none()->borrow(Money::fromEuros(24000.0)));

        // Day 0 is January 1st: the 100 € installment is due and paid.
        $jan1 = $engine->snapshot($config, $state);
        self::assertSame(100_00, $jan1->loanPayment->cents);

        $after = $engine->advance($config, $state);
        self::assertSame(24000_00 - 100_00, $after->loan->remaining->cents);

        // January 2nd: nothing due.
        self::assertSame(0, $engine->snapshot($config, $after)->loanPayment->cents);
    }

    public function testFuelOilHeatingDoesNotTouchTheElectricLoop(): void
    {
        $engine = new SimulationEngine();
        $config = self::config();

        $fioul = $engine->snapshot($config, GameState::start(self::passoire(), Money::fromEuros(8000.0)));

        self::assertGreaterThan(0.0, $fioul->heating->fuelOilLitres, 'January in a passoire burns fuel oil.');
        self::assertSame(0.0, $fioul->heating->electricityKwh);
    }

    public function testHeatPumpHeatingFlowsIntoTheElectricDemand(): void
    {
        $engine = new SimulationEngine();
        $config = self::config();

        $fioulHome = new Household(3.0, 5.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler);
        $heatPumpHome = new Household(3.0, 5.0, InsulationLevel::Original, HeatingSystem::HeatPump);

        $fioul = $engine->snapshot($config, GameState::start($fioulHome, Money::fromEuros(8000.0)));
        $heatPump = $engine->snapshot($config, GameState::start($heatPumpHome, Money::fromEuros(8000.0)));

        self::assertGreaterThan(
            $fioul->balance->demandKwh,
            $heatPump->balance->demandKwh,
            'Electrified heating raises the electric demand (game-design §12).',
        );
        self::assertSame(0.0, $heatPump->heating->fuelOilLitres);
    }

    public function testWeatherAdvancesWithTheDay(): void
    {
        $engine = new SimulationEngine();
        $config = self::config();

        $day0 = $engine->snapshot($config, GameState::start(self::passoire(), Money::fromEuros(8000.0)));
        $day1 = $engine->snapshot($config, $engine->advance($config, GameState::start(self::passoire(), Money::fromEuros(8000.0))));

        self::assertSame('2025-01-01', $day0->date->format());
        self::assertSame('2025-01-02', $day1->date->format());
    }

    public function testIsFinishedAtHorizonAndAdvanceIsANoOp(): void
    {
        $engine = new SimulationEngine();
        $config = self::config(3);
        $atHorizon = new GameState(3, self::passoire(), 0.0, Money::zero(), Loan::none(), new PeriodTotals());

        self::assertTrue($engine->isFinished($config, $atHorizon));
        self::assertSame(3, $engine->advance($config, $atHorizon)->currentDay, 'A finished game does not advance.');
    }

    public function testTheBoilerBreaksOnTheScriptedDay(): void
    {
        $engine = new SimulationEngine();
        $config = self::config(30); // Default breakdown day: 19 (January 20th).
        $state = GameState::start(self::passoire(), Money::fromEuros(4000.0));

        while ($state->currentDay < 19) {
            $state = $engine->advance($config, $state);
            if ($state->currentDay < 19) {
                self::assertFalse($state->household->boilerBroken, "Day {$state->currentDay}: still fine.");
            }
        }

        self::assertTrue($state->household->boilerBroken, 'January 20th morning: the boiler is dead.');

        $coldDay = $engine->snapshot($config, $state);
        self::assertSame(0.0, $coldDay->heating->fuelOilLitres, 'A dead boiler burns nothing.');
        self::assertSame(0.0, $coldDay->heating->needKwh);
        self::assertLessThan(10.0, $coldDay->comfort->indoorC, 'The unheated January house is freezing.');
        self::assertSame(0, $coldDay->comfort->score);
        self::assertSame(0, $coldDay->bill->fuelOilCost->cents, 'No fuel burnt, no fuel billed.');
    }

    public function testARepairedBoilerDoesNotBreakAgain(): void
    {
        $engine = new SimulationEngine();
        $config = self::config(30);
        $broken = new GameState(19, self::passoire()->withBoilerBroken(true), 0.0, Money::fromEuros(4000.0), Loan::none(), new PeriodTotals());

        $repaired = $broken->withHousehold($broken->household->withBoilerBroken(false));
        $nextDay = $engine->advance($config, $repaired);

        self::assertFalse($nextDay->household->boilerBroken, 'The scripted event fires once — a scene, not a wear model.');
    }

    public function testSwitchingToTheHeatPumpBeforeTheEventAvoidsIt(): void
    {
        $engine = new SimulationEngine();
        $config = self::config(30);
        $heatPumpHome = new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::HeatPump);
        $state = GameState::start($heatPumpHome, Money::fromEuros(4000.0));

        while (!$engine->isFinished($config, $state)) {
            $state = $engine->advance($config, $state);
        }

        self::assertFalse($state->household->boilerBroken, 'Anticipating the switch means never living the breakdown.');
    }

    public function testGamePlaysToTheHorizon(): void
    {
        $engine = new SimulationEngine();
        $config = self::config(10);
        $state = GameState::start(self::passoire(), Money::fromEuros(8000.0));

        while (!$engine->isFinished($config, $state)) {
            $state = $engine->advance($config, $state);
        }

        self::assertSame(10, $state->currentDay);
        self::assertGreaterThan(0.0, $state->totals->productionKwh);
        self::assertGreaterThan(0.0, $state->totals->fuelOilLitres, 'Ten January days in a fioul passoire burn oil.');
        self::assertSame(10, $state->totals->days);
    }
}
