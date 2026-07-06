<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Time;

use App\Domain\Time\Season;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SeasonTest extends TestCase
{
    #[DataProvider('monthProvider')]
    public function testFromMonth(int $month, Season $expected): void
    {
        self::assertSame($expected, Season::fromMonth($month));
    }

    /**
     * @return iterable<string, array{int, Season}>
     */
    public static function monthProvider(): iterable
    {
        yield 'December is winter' => [12, Season::Winter];
        yield 'January is winter' => [1, Season::Winter];
        yield 'February is winter' => [2, Season::Winter];
        yield 'March is spring' => [3, Season::Spring];
        yield 'May is spring' => [5, Season::Spring];
        yield 'June is summer' => [6, Season::Summer];
        yield 'August is summer' => [8, Season::Summer];
        yield 'September is autumn' => [9, Season::Autumn];
        yield 'November is autumn' => [11, Season::Autumn];
    }

    public function testFromMonthRejectsInvalidMonth(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Season::fromMonth(13); // @phpstan-ignore-line intentional out-of-range value
    }

    public function testEverySeasonHasANonEmptyLabel(): void
    {
        foreach (Season::cases() as $season) {
            self::assertNotSame('', $season->label());
        }
    }
}
