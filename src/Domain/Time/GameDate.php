<?php

declare(strict_types=1);

namespace App\Domain\Time;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Immutable in-game calendar date.
 *
 * A game runs as a sequence of daily ticks. Internally a game only needs a day
 * counter (the tick index), but the player sees a real calendar date and the
 * simulation needs the day-of-year to drive seasonality (§5). This value object
 * ties those together around a fixed epoch, staying framework-free and
 * deterministic (same day index → same date, always).
 *
 * It wraps PHP's native {@see \DateTimeImmutable} (core PHP, not a framework
 * dependency) normalised to a UTC calendar day with no time component.
 */
final readonly class GameDate
{
    /**
     * @param int<0, max> $dayIndex
     */
    private function __construct(
        private DateTimeImmutable $date,
        private int $dayIndex,
    ) {
    }

    /**
     * First day of a game (day index 0), on the given epoch date.
     */
    public static function epoch(DateTimeImmutable $epoch): self
    {
        return new self(self::normalise($epoch), 0);
    }

    /**
     * The day at position $dayIndex (0-based) counted from $epoch.
     *
     * @param int<0, max> $dayIndex
     */
    public static function fromDayIndex(DateTimeImmutable $epoch, int $dayIndex): self
    {
        if ($dayIndex < 0) {
            throw new InvalidArgumentException("Day index cannot be negative: {$dayIndex}");
        }

        $date = self::normalise($epoch)->add(new DateInterval("P{$dayIndex}D"));

        return new self($date, $dayIndex);
    }

    /**
     * The next calendar day (one tick later).
     */
    public function next(): self
    {
        return new self($this->date->add(new DateInterval('P1D')), $this->dayIndex + 1);
    }

    /**
     * Zero-based tick index since the game epoch.
     *
     * @return int<0, max>
     */
    public function dayIndex(): int
    {
        return $this->dayIndex;
    }

    public function toDateTime(): DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * Day of the year, 1 (1 Jan) to 365 or 366 (31 Dec).
     *
     * @return int<1, 366>
     */
    public function dayOfYear(): int
    {
        /** @var int<1, 366> $dayOfYear */
        $dayOfYear = 1 + (int) $this->date->format('z');

        return $dayOfYear;
    }

    public function season(): Season
    {
        /** @var int<1, 12> $month */
        $month = (int) $this->date->format('n');

        return Season::fromMonth($month);
    }

    public function format(string $format = 'Y-m-d'): string
    {
        return $this->date->format($format);
    }

    private static function normalise(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date
            ->setTimezone(new DateTimeZone('UTC'))
            ->setTime(0, 0);
    }
}
