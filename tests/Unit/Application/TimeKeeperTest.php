<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Game;
use App\Application\TimeKeeper;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
use App\Domain\Finance\Loan;
use App\Domain\Finance\Money;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\PeriodTotals;
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

    public function testTheBoilerBreakdownAutoPausesOnItsVeryMorning(): void
    {
        // Day 17, ×3, 90 s elapsed = 9 due days — but the boiler dies on day 19.
        $game = self::gameAtDay(17, TickSpeed::Triple);

        $caughtUp = new TimeKeeper()->catchUp($game, new DateTimeImmutable('2026-01-01 12:01:30'));

        self::assertSame(19, $caughtUp->state->currentDay, 'The catch-up freezes on the breakdown morning.');
        self::assertTrue($caughtUp->state->household->boilerBroken);
        self::assertSame(TickSpeed::Paused, $caughtUp->progression->speed, 'Deciding deserves a stopped clock.');
    }

    public function testResumingAfterTheBreakdownDoesNotPauseAgain(): void
    {
        $broken = new TimeKeeper()->catchUp(self::gameAtDay(18, TickSpeed::Normal), new DateTimeImmutable('2026-01-01 12:00:30'));
        self::assertSame(TickSpeed::Paused, $broken->progression->speed);

        // The player resumes at ×1 without repairing: cold days pass, no re-pause.
        $resumed = $broken->withProgression($broken->progression->withSpeed(TickSpeed::Normal, new DateTimeImmutable('2026-01-01 12:05:00')));
        $later = new TimeKeeper()->catchUp($resumed, new DateTimeImmutable('2026-01-01 12:06:00'));

        self::assertSame(21, $later->state->currentDay);
        self::assertSame(TickSpeed::Normal, $later->progression->speed);
    }

    public function testManualStepLivesOneDayAndRestartsTheClock(): void
    {
        $stepped = new TimeKeeper()->step(self::game(TickSpeed::Normal), new DateTimeImmutable('2026-01-01 12:00:10'));

        self::assertSame(1, $stepped->state->currentDay);
        self::assertSame('12:00:10', $stepped->progression->lastTickAt->format('H:i:s'), 'The 10 s in progress are not counted twice.');
        self::assertSame(TickSpeed::Normal, $stepped->progression->speed);
    }

    public function testManualStepIntoTheBreakdownAlsoPauses(): void
    {
        $stepped = new TimeKeeper()->step(self::gameAtDay(18, TickSpeed::Paused), new DateTimeImmutable('2026-01-01 12:00:05'));

        self::assertSame(19, $stepped->state->currentDay);
        self::assertTrue($stepped->state->household->boilerBroken);
        self::assertSame(TickSpeed::Paused, $stepped->progression->speed);
    }

    private static function gameAtDay(int $day, TickSpeed $speed): Game
    {
        return new Game(
            new GameConfig(seed: 7, epoch: new DateTimeImmutable('2025-01-01'), horizonDays: 365),
            new GameState(
                $day,
                new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler),
                0.0,
                Money::fromEuros(7750.0),
                Loan::none(),
                new PeriodTotals(),
            ),
            new TimeProgression(new DateTimeImmutable('2026-01-01 12:00:00'), $speed),
        );
    }
}
