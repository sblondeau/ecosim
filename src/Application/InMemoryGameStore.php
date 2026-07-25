<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Scenario\PrimoAccedantScenario;
use App\Domain\Scenario\Scenario;
use App\Domain\Simulation\GameConfig;
use App\Domain\Time\TimeProgression;
use DateTimeImmutable;

/**
 * A process-memory {@see GameStore} for tests: it lets a test seed any game
 * state directly (no HTTP session, which only exists inside a request), so
 * "given an installed household, the drawer renders X" can be checked without
 * driving the real-time timeline into scripted events.
 *
 * The store is held in a STATIC field so it survives the KernelBrowser
 * rebooting the kernel between the LiveComponent test's sub-requests (a fresh
 * store service instance still reads the same game). Tests must call
 * {@see self::clear()} in their setUp to stay isolated.
 *
 * A fixed seed keeps weather deterministic across a test run.
 */
final class InMemoryGameStore implements GameStore
{
    private const int FIXED_SEED = 424242;
    private const string EPOCH = '2025-01-01';

    private static ?Game $stored = null;

    public function __construct(
        private readonly Scenario $scenario = new PrimoAccedantScenario(),
    ) {
    }

    public function current(): Game
    {
        return self::$stored ??= $this->fresh();
    }

    public function save(Game $game): void
    {
        self::$stored = $game;
    }

    public function reset(): Game
    {
        return self::$stored = $this->fresh();
    }

    /** Drop any seeded/played game so the next test starts clean. */
    public static function clear(): void
    {
        self::$stored = null;
    }

    private function fresh(): Game
    {
        $config = new GameConfig(
            seed: self::FIXED_SEED,
            epoch: new DateTimeImmutable(self::EPOCH),
            horizonDays: $this->scenario->horizonDays(),
        );

        return new Game(
            $config,
            $this->scenario->initialState(),
            TimeProgression::startingAt(new DateTimeImmutable()),
        );
    }
}
