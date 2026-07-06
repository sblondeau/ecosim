<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Time;

use App\Domain\Time\GameDate;
use App\Domain\Time\Season;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GameDateTest extends TestCase
{
    private static function epoch(string $date = '2025-01-01'): DateTimeImmutable
    {
        $epoch = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        self::assertInstanceOf(DateTimeImmutable::class, $epoch);

        return $epoch;
    }

    public function testEpochStartsAtDayIndexZero(): void
    {
        $date = GameDate::epoch(self::epoch());

        self::assertSame(0, $date->dayIndex());
        self::assertSame('2025-01-01', $date->format());
        self::assertSame(1, $date->dayOfYear());
    }

    public function testNextAdvancesOneDay(): void
    {
        $date = GameDate::epoch(self::epoch())->next();

        self::assertSame(1, $date->dayIndex());
        self::assertSame('2025-01-02', $date->format());
    }

    public function testNextIsImmutable(): void
    {
        $date = GameDate::epoch(self::epoch());
        $date->next();

        self::assertSame(0, $date->dayIndex(), 'next() must not mutate the original');
    }

    public function testFromDayIndexCrossesMonthBoundary(): void
    {
        $date = GameDate::fromDayIndex(self::epoch(), 31);

        self::assertSame('2025-02-01', $date->format());
        self::assertSame(32, $date->dayOfYear());
    }

    public function testFromDayIndexRejectsNegativeIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GameDate::fromDayIndex(self::epoch(), -1);
    }

    public function testSeasonFollowsTheCalendar(): void
    {
        $winter = GameDate::epoch(self::epoch('2025-01-15'));
        $summer = GameDate::epoch(self::epoch('2025-07-15'));

        self::assertSame(Season::Winter, $winter->season());
        self::assertSame(Season::Summer, $summer->season());
    }

    public function testEpochNormalisesTimeAndTimezone(): void
    {
        $noisyEpoch = new DateTimeImmutable('2025-01-01 18:42:07', new DateTimeZone('Europe/Paris'));

        $date = GameDate::epoch($noisyEpoch);

        // Normalised to the UTC calendar day, no time component leaking in.
        self::assertSame('2025-01-01 00:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testDayOfYearHandlesLeapYearEnd(): void
    {
        // 2024 is a leap year → 366 days.
        $date = GameDate::fromDayIndex(self::epoch('2024-01-01'), 365);

        self::assertSame('2024-12-31', $date->format());
        self::assertSame(366, $date->dayOfYear());
    }
}
