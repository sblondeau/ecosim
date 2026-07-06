<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Energy;

use App\Domain\Energy\EnergyDemandCalculator;
use App\Domain\Time\GameDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EnergyDemandCalculatorTest extends TestCase
{
    private static function date(string $date): GameDate
    {
        $epoch = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        self::assertInstanceOf(DateTimeImmutable::class, $epoch);

        return GameDate::epoch($epoch);
    }

    public function testDemandIsPositive(): void
    {
        $calculator = new EnergyDemandCalculator();

        self::assertGreaterThan(0.0, $calculator->dailyDemandKwh(self::date('2025-05-01')));
    }

    public function testWinterDemandExceedsSummerDemand(): void
    {
        $calculator = new EnergyDemandCalculator();

        self::assertGreaterThan(
            $calculator->dailyDemandKwh(self::date('2025-07-15')),
            $calculator->dailyDemandKwh(self::date('2025-01-15')),
        );
    }
}
