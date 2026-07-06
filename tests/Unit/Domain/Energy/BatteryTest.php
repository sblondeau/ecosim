<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Energy;

use App\Domain\Energy\Battery;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BatteryTest extends TestCase
{
    public function testNoneIsNotInstalled(): void
    {
        $battery = Battery::none();

        self::assertFalse($battery->isInstalled());
        self::assertSame(0.0, $battery->capacityKwh);
    }

    public function testOfIsSymmetricAndInstalled(): void
    {
        $battery = Battery::of(5.0, 0.95);

        self::assertTrue($battery->isInstalled());
        self::assertSame(5.0, $battery->capacityKwh);
        self::assertSame(0.95, $battery->chargeEfficiency);
        self::assertSame(0.95, $battery->dischargeEfficiency);
    }

    public function testRejectsNegativeCapacity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Battery(-1.0, 0.9, 0.9);
    }

    public function testRejectsEfficiencyAboveOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Battery(5.0, 1.2, 0.9);
    }

    public function testRejectsZeroEfficiency(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Battery(5.0, 0.9, 0.0);
    }
}
