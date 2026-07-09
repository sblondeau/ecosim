<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Game;
use App\Application\TimeKeeper;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
use App\Domain\Finance\Money;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Time\TickSpeed;
use App\Domain\Time\TimeProgression;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TimeKeeperTest extends TestCase
{
    private static function game(TickSpeed $speed, int $horizonDays = 365): Game
    {
        return new Game(
            new GameConfig(seed: 7, epoch: new DateTimeImmutable('2025-01-01'), horizonDays: $horizonDays),
            GameState::start(
                new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler),
                Money::fromEuros(7750.0),
            ),
            new TimeProgression(new DateTimeImmutable('2026-01-01 12:00:00'), $speed),
        );
    }

    public function testLivesTheDueDaysThroughTheEngine(): void
    {
        // 65 s at ×1 = 2 due days.
        $caughtUp = new TimeKeeper()->catchUp(self::game(TickSpeed::Normal), new DateTimeImmutable('2026-01-01 12:01:05'));

        self::assertSame(2, $caughtUp->state->currentDay);
        self::assertSame(2, $caughtUp->state->totals->days, 'The days were lived, not skipped.');
        self::assertSame('12:01:00', $caughtUp->progression->lastTickAt->format('H:i:s'), 'The 5 s remainder carries over.');
    }

    public function testPausedGamesDoNotMove(): void
    {
        $caughtUp = new TimeKeeper()->catchUp(self::game(TickSpeed::Paused), new DateTimeImmutable('2026-01-01 12:05:00'));

        self::assertSame(0, $caughtUp->state->currentDay);
    }

    public function testCatchingUpStopsAtTheHorizon(): void
    {
        // 90 s at ×3 = 9 due days, but the game ends after 5.
        $caughtUp = new TimeKeeper()->catchUp(self::game(TickSpeed::Triple, horizonDays: 5), new DateTimeImmutable('2026-01-01 12:01:30'));

        self::assertSame(5, $caughtUp->state->currentDay);
    }
}
