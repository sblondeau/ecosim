<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Weather;

use App\Domain\Time\GameDate;
use App\Domain\Weather\WeatherGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WeatherGeneratorTest extends TestCase
{
    private const int SEED = 2025;

    private static function epoch(string $date): GameDate
    {
        $epoch = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        self::assertInstanceOf(DateTimeImmutable::class, $epoch);

        return GameDate::epoch($epoch);
    }

    public function testIsDeterministicForTheSameSeedAndDay(): void
    {
        $generator = new WeatherGenerator();
        $date = self::epoch('2025-03-10');

        $first = $generator->for(self::SEED, $date);
        $second = $generator->for(self::SEED, $date);

        self::assertSame($first->cloudCover, $second->cloudCover);
        self::assertSame($first->temperatureC, $second->temperatureC);
    }

    public function testDifferentSeedsDivergeAtLeastOnce(): void
    {
        $generator = new WeatherGenerator();
        $date = self::epoch('2025-01-01');

        $diverged = false;
        for ($i = 0; $i < 30; ++$i) {
            $a = $generator->for(1, $date);
            $b = $generator->for(2, $date);
            if ($a->temperatureC !== $b->temperatureC || $a->cloudCover !== $b->cloudCover) {
                $diverged = true;
                break;
            }
            $date = $date->next();
        }

        self::assertTrue($diverged, 'Two different seeds should not produce identical weather every day.');
    }

    public function testJulyIsWarmerThanJanuaryOnAverage(): void
    {
        $generator = new WeatherGenerator();

        self::assertGreaterThan(
            $this->averageTemperature($generator, self::epoch('2025-01-01'), 31),
            $this->averageTemperature($generator, self::epoch('2025-07-01'), 31),
        );
    }

    public function testWinterIsCloudierThanSummerOnAverage(): void
    {
        $generator = new WeatherGenerator();

        self::assertGreaterThan(
            $this->averageCloudCover($generator, self::epoch('2025-07-01'), 31),
            $this->averageCloudCover($generator, self::epoch('2025-01-01'), 31),
        );
    }

    public function testStaysWithinPlausibleBoundsAcrossAFullYear(): void
    {
        $generator = new WeatherGenerator();
        $date = self::epoch('2025-01-01');

        for ($i = 0; $i < 366; ++$i) {
            $weather = $generator->for(self::SEED, $date);

            // cloudCover in [0, 1] is guaranteed by the Weather VO constructor;
            // this asserts the generator never leaves a plausible temperature band.
            self::assertGreaterThan(-20.0, $weather->temperatureC);
            self::assertLessThan(45.0, $weather->temperatureC);
            $date = $date->next();
        }
    }

    public function testTemperatureMovesGraduallyColdSpellsPersist(): void
    {
        $generator = new WeatherGenerator();
        $date = self::epoch('2025-01-01');

        $maxDayToDayJump = 0.0;
        $previous = $generator->for(self::SEED, $date)->temperatureC;
        for ($i = 1; $i < 366; ++$i) {
            $date = $date->next();
            $current = $generator->for(self::SEED, $date)->temperatureC;
            $maxDayToDayJump = max($maxDayToDayJump, abs($current - $previous));
            $previous = $current;
        }

        // Smooth persistent noise: no white-noise zapping (which could jump
        // ~8 °C overnight). Regimes settle in and drift over days.
        self::assertLessThan(4.0, $maxDayToDayJump);
    }

    private function averageTemperature(WeatherGenerator $generator, GameDate $start, int $days): float
    {
        $sum = 0.0;
        $date = $start;
        for ($i = 0; $i < $days; ++$i) {
            $sum += $generator->for(self::SEED, $date)->temperatureC;
            $date = $date->next();
        }

        return $sum / $days;
    }

    private function averageCloudCover(WeatherGenerator $generator, GameDate $start, int $days): float
    {
        $sum = 0.0;
        $date = $start;
        for ($i = 0; $i < $days; ++$i) {
            $sum += $generator->for(self::SEED, $date)->cloudCover;
            $date = $date->next();
        }

        return $sum / $days;
    }
}
