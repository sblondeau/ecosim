<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Math;

use App\Domain\Math\SeasonalCycle;
use PHPUnit\Framework\TestCase;

final class SeasonalCycleTest extends TestCase
{
    public function testPeaksAtOneOnThePeakDay(): void
    {
        self::assertEqualsWithDelta(1.0, SeasonalCycle::cosine(172, 172.0), 1e-9);
    }

    public function testReachesMinusOneHalfAYearLater(): void
    {
        self::assertEqualsWithDelta(-1.0, SeasonalCycle::cosine(172 + 183, 172.0), 1e-2);
    }

    public function testIsSymmetricAroundThePeak(): void
    {
        $before = SeasonalCycle::cosine(172 - 40, 172.0);
        $after = SeasonalCycle::cosine(172 + 40, 172.0);

        self::assertEqualsWithDelta($before, $after, 1e-9);
    }
}
