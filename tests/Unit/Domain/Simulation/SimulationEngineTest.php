<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Simulation;

use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
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
        return new Household(3.0, 5.0, InsulationLevel::None, HeatingSystem::FuelOilBoiler);
    }

    public function testSnapshotIsDeterministic(): void
    {
        $engine = new SimulationEngine();
        $config = self::config();
        $state = GameState::start(self::passoire());

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
        $state = GameState::start(self::passoire());

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
        $state = GameState::start(self::passoire());

        $next = $engine->advance(self::config(), $state);

        self::assertSame($state->household, $next->household);
    }

    public function testFuelOilHeatingDoesNotTouchTheElectricLoop(): void
    {
        $engine = new SimulationEngine();
        $config = self::config();

        $fioul = $engine->snapshot($config, GameState::start(self::passoire()));

        self::assertGreaterThan(0.0, $fioul->heating->fuelOilLitres, 'January in a passoire burns fuel oil.');
        self::assertSame(0.0, $fioul->heating->electricityKwh);
    }

    public function testHeatPumpHeatingFlowsIntoTheElectricDemand(): void
    {
        $engine = new SimulationEngine();
        $config = self::config();

        $fioulHome = new Household(3.0, 5.0, InsulationLevel::None, HeatingSystem::FuelOilBoiler);
        $heatPumpHome = new Household(3.0, 5.0, InsulationLevel::None, HeatingSystem::HeatPump);

        $fioul = $engine->snapshot($config, GameState::start($fioulHome));
        $heatPump = $engine->snapshot($config, GameState::start($heatPumpHome));

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

        $day0 = $engine->snapshot($config, GameState::start(self::passoire()));
        $day1 = $engine->snapshot($config, $engine->advance($config, GameState::start(self::passoire())));

        self::assertSame('2025-01-01', $day0->date->format());
        self::assertSame('2025-01-02', $day1->date->format());
    }

    public function testIsFinishedAtHorizonAndAdvanceIsANoOp(): void
    {
        $engine = new SimulationEngine();
        $config = self::config(3);
        $atHorizon = new GameState(3, self::passoire(), 0.0, new PeriodTotals());

        self::assertTrue($engine->isFinished($config, $atHorizon));
        self::assertSame(3, $engine->advance($config, $atHorizon)->currentDay, 'A finished game does not advance.');
    }

    public function testGamePlaysToTheHorizon(): void
    {
        $engine = new SimulationEngine();
        $config = self::config(10);
        $state = GameState::start(self::passoire());

        while (!$engine->isFinished($config, $state)) {
            $state = $engine->advance($config, $state);
        }

        self::assertSame(10, $state->currentDay);
        self::assertGreaterThan(0.0, $state->totals->productionKwh);
        self::assertGreaterThan(0.0, $state->totals->fuelOilLitres, 'Ten January days in a fioul passoire burn oil.');
        self::assertSame(10, $state->totals->days);
    }
}
