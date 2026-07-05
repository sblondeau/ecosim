<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Energy;

use App\Domain\Energy\SolarProductionCalculator;
use App\Domain\Time\GameDate;
use App\Domain\Weather\Weather;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SolarProductionCalculatorTest extends TestCase
{
    private const float PEAK_KWC = 3.0;

    private static function date(string $date): GameDate
    {
        $epoch = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        self::assertInstanceOf(DateTimeImmutable::class, $epoch);

        return GameDate::epoch($epoch);
    }

    public function testNoPanelsMeansNoProduction(): void
    {
        $calculator = new SolarProductionCalculator();

        self::assertSame(0.0, $calculator->dailyProductionKwh(0.0, new Weather(0.0, 20.0), self::date('2025-06-21')));
    }

    public function testClearSkyProducesMoreThanOvercast(): void
    {
        $calculator = new SolarProductionCalculator();
        $date = self::date('2025-06-21');

        $clear = $calculator->dailyProductionKwh(self::PEAK_KWC, new Weather(0.0, 25.0), $date);
        $overcast = $calculator->dailyProductionKwh(self::PEAK_KWC, new Weather(1.0, 18.0), $date);

        self::assertGreaterThan($overcast, $clear);
        self::assertGreaterThan(0.0, $overcast, 'Overcast still yields some diffuse production.');
    }

    public function testSummerProducesMoreThanWinter(): void
    {
        $calculator = new SolarProductionCalculator();
        $clearSky = new Weather(0.0, 15.0);

        $summer = $calculator->dailyProductionKwh(self::PEAK_KWC, $clearSky, self::date('2025-06-21'));
        $winter = $calculator->dailyProductionKwh(self::PEAK_KWC, $clearSky, self::date('2025-12-21'));

        self::assertGreaterThan($winter, $summer);
    }

    public function testIsDeterministic(): void
    {
        $calculator = new SolarProductionCalculator();
        $date = self::date('2025-04-15');
        $weather = new Weather(0.3, 14.0);

        self::assertSame(
            $calculator->dailyProductionKwh(self::PEAK_KWC, $weather, $date),
            $calculator->dailyProductionKwh(self::PEAK_KWC, $weather, $date),
        );
    }
}
