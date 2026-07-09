<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Time;

use App\Domain\Time\TickSpeed;
use App\Domain\Time\TimeProgression;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TimeProgressionTest extends TestCase
{
    private static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable("2026-01-01 {$time}");
    }

    public function testNoDayIsDueBeforeTheBasePaceElapses(): void
    {
        $progression = new TimeProgression(self::at('12:00:00'), TickSpeed::Normal);

        $result = $progression->tick(self::at('12:00:29'));

        self::assertSame(0, $result->days);
        self::assertSame($progression, $result->progression, 'Nothing consumed, nothing changes.');
    }

    public function testOneDayEveryThirtySecondsAtNormalSpeed(): void
    {
        $result = new TimeProgression(self::at('12:00:00'), TickSpeed::Normal)->tick(self::at('12:00:30'));

        self::assertSame(1, $result->days);
        self::assertSame('12:00:30', $result->progression->lastTickAt->format('H:i:s'));
    }

    public function testTheRemainderCarriesOverBetweenTicks(): void
    {
        // 65 s at ×1 = 2 days consumed (60 s), 5 s kept for the next tick.
        $result = new TimeProgression(self::at('12:00:00'), TickSpeed::Normal)->tick(self::at('12:01:05'));

        self::assertSame(2, $result->days);
        self::assertSame('12:01:00', $result->progression->lastTickAt->format('H:i:s'), 'Only whole days are consumed.');

        // 25 more seconds: the carried 5 s complete a third day.
        $next = $result->progression->tick(self::at('12:01:30'));
        self::assertSame(1, $next->days);
    }

    public function testFasterSpeedsShortenTheDay(): void
    {
        $start = self::at('12:00:00');

        self::assertSame(2, new TimeProgression($start, TickSpeed::Double)->tick(self::at('12:00:30'))->days, '×2: a day every 15 s.');
        self::assertSame(3, new TimeProgression($start, TickSpeed::Triple)->tick(self::at('12:00:30'))->days, '×3: a day every 10 s.');
    }

    public function testPausedCreditsNothingAndFollowsTheClock(): void
    {
        $result = new TimeProgression(self::at('12:00:00'), TickSpeed::Paused)->tick(self::at('12:05:00'));

        self::assertSame(0, $result->days);
        self::assertSame('12:05:00', $result->progression->lastTickAt->format('H:i:s'), 'Resuming later must not credit the paused stretch.');
    }

    public function testAbsenceBeyondTheGraceCreditsNothing(): void
    {
        // PausesWhileAway (acted decision): 10 minutes away is not 20 game days.
        $result = new TimeProgression(self::at('12:00:00'), TickSpeed::Normal)->tick(self::at('12:10:00'));

        self::assertSame(0, $result->days);
        self::assertSame('12:10:00', $result->progression->lastTickAt->format('H:i:s'), 'The clock restarts at the return.');
    }

    public function testClockGoingBackwardsIsIgnored(): void
    {
        $progression = new TimeProgression(self::at('12:00:00'), TickSpeed::Normal);

        self::assertSame(0, $progression->tick(self::at('11:59:00'))->days);
    }

    public function testChangingSpeedRestartsTheClock(): void
    {
        $progression = new TimeProgression(self::at('12:00:00'), TickSpeed::Normal)
            ->withSpeed(TickSpeed::Triple, self::at('12:00:20'));

        self::assertSame(TickSpeed::Triple, $progression->speed);
        self::assertSame('12:00:20', $progression->lastTickAt->format('H:i:s'), 'The partial day at the old pace is dropped.');
    }

    public function testTheBasePaceIsDivisibleByEveryMultiplier(): void
    {
        foreach (TickSpeed::cases() as $speed) {
            if (TickSpeed::Paused === $speed) {
                continue;
            }

            self::assertSame(
                0,
                TimeProgression::SECONDS_PER_GAME_DAY % $speed->multiplier(),
                "A game day must be a whole number of seconds at {$speed->label()}.",
            );
        }
    }
}
