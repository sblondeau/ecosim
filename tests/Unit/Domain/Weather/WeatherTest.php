<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Weather;

use App\Domain\Weather\Weather;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class WeatherTest extends TestCase
{
    public function testExposesCloudCoverAndTemperature(): void
    {
        $weather = new Weather(0.25, 14.3);

        self::assertSame(0.25, $weather->cloudCover);
        self::assertSame(14.3, $weather->temperatureC);
    }

    public function testRejectsCloudCoverBelowZero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Weather(-0.1, 10.0);
    }

    public function testRejectsCloudCoverAboveOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Weather(1.1, 10.0);
    }
}
