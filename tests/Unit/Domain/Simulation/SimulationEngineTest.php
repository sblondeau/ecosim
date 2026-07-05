<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Simulation;

use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
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
            solarKwc: 3.0,
            batteryKwh: 5.0,
            horizonDays: $horizonDays,
        );
    }

    public function testSnapshotIsDeterministic(): void
    {
        $engine = new SimulationEngine();
        $config = self::config();
        $state = GameState::start();

        $a = $engine->snapshot($config, $state);
        $b = $engine->snapshot($config, $state);

        self::assertSame($a->balance->productionKwh, $b->balance->productionKwh);
        self::assertSame($a->weather->temperatureC, $b->weather->temperatureC);
    }

    public function testAdvanceMovesToNextDayAndFoldsTheBalance(): void
    {
        $engine = new SimulationEngine();
        $config = self::config();
        $state = GameState::start();

        $snapshot = $engine->snapshot($config, $state);
        $next = $engine->advance($config, $state);

        self::assertSame(1, $next->currentDay);
        self::assertSame($snapshot->balance->batteryLevelKwh, $next->batteryLevelKwh);
        self::assertSame($snapshot->balance->productionKwh, $next->totals->productionKwh);
        self::assertSame($snapshot->balance->gridImportKwh, $next->totals->importKwh);
    }

    public function testWeatherAdvancesWithTheDay(): void
    {
        $engine = new SimulationEngine();
        $config = self::config();

        $day0 = $engine->snapshot($config, GameState::start());
        $day1 = $engine->snapshot($config, $engine->advance($config, GameState::start()));

        self::assertSame('2025-01-01', $day0->date->format());
        self::assertSame('2025-01-02', $day1->date->format());
    }

    public function testIsFinishedAtHorizonAndAdvanceIsANoOp(): void
    {
        $engine = new SimulationEngine();
        $config = self::config(3);
        $atHorizon = new GameState(3, 0.0, new \App\Domain\Simulation\PeriodTotals());

        self::assertTrue($engine->isFinished($config, $atHorizon));
        self::assertSame(3, $engine->advance($config, $atHorizon)->currentDay, 'A finished game does not advance.');
    }

    public function testGamePlaysToTheHorizon(): void
    {
        $engine = new SimulationEngine();
        $config = self::config(10);
        $state = GameState::start();

        while (!$engine->isFinished($config, $state)) {
            $state = $engine->advance($config, $state);
        }

        self::assertSame(10, $state->currentDay);
        self::assertGreaterThan(0.0, $state->totals->productionKwh);
    }
}
