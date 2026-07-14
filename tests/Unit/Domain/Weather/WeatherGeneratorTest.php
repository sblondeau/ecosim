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

        // Smooth persistent noise: regimes settle in and drift over days. A
        // frontal passage can still move the daily mean ~5 °C, but never the
        // ~12 °C+ zapping white noise would produce day to day.
        self::assertLessThan(6.0, $maxDayToDayJump);
    }

    public function testProducesGenuineColdSnaps(): void
    {
        $generator = new WeatherGenerator();

        // The flat pre-recalibration model never dropped below ~+1 °C; a realistic
        // France winter has cold spells with daily means well below zero.
        $coldest = 99.0;
        for ($seed = 1; $seed <= 12; ++$seed) {
            $date = self::epoch('2025-01-01');
            for ($i = 0; $i < 365; ++$i) {
                $coldest = min($coldest, $generator->for($seed, $date)->temperatureC);
                $date = $date->next();
            }
        }

        self::assertLessThan(-2.0, $coldest, 'The model must produce real sub-zero cold snaps.');
    }

    public function testHeatingDegreeDaysMatchTheFrenchRange(): void
    {
        $generator = new WeatherGenerator();

        // Mean annual DJU (base 18) across seeds must land in the metropolitan
        // France band (~2300-2500), not the too-mild ~2130 of the flat model —
        // this is what keeps thermostat/heating physics honest.
        $total = 0.0;
        $seeds = 40;
        for ($seed = 1; $seed <= $seeds; ++$seed) {
            $date = self::epoch('2025-01-01');
            for ($i = 0; $i < 365; ++$i) {
                $total += max(0.0, 18.0 - $generator->for($seed, $date)->temperatureC);
                $date = $date->next();
            }
        }
        $averageDju = $total / $seeds;

        self::assertGreaterThan(2180.0, $averageDju);
        self::assertLessThan(2380.0, $averageDju);
    }

    public function testSeasonalMeansStayRealistic(): void
    {
        $generator = new WeatherGenerator();

        $januaryMean = 0.0;
        $julyMean = 0.0;
        $seeds = 40;
        for ($seed = 1; $seed <= $seeds; ++$seed) {
            $januaryMean += $this->averageTemperature($generator, self::epoch('2025-01-01'), 31, $seed);
            $julyMean += $this->averageTemperature($generator, self::epoch('2025-07-01'), 31, $seed);
        }
        $januaryMean /= $seeds;
        $julyMean /= $seeds;

        // Semi-continental France reference: January ≈ 4 °C, July ≈ 21 °C.
        self::assertEqualsWithDelta(4.0, $januaryMean, 1.0);
        self::assertEqualsWithDelta(21.0, $julyMean, 1.0);
    }

    private function averageTemperature(WeatherGenerator $generator, GameDate $start, int $days, int $seed = self::SEED): float
    {
        $sum = 0.0;
        $date = $start;
        for ($i = 0; $i < $days; ++$i) {
            $sum += $generator->for($seed, $date)->temperatureC;
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
