<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\DpeClass;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HouseholdTest extends TestCase
{
    public function testExposesItsConfiguration(): void
    {
        $household = new Household(3.0, 5.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler);

        self::assertSame(3.0, $household->solarKwc);
        self::assertSame(5.0, $household->batteryKwh);
        self::assertSame(DpeClass::G, $household->dpeClass(), 'The starting passoire is a G.');
    }

    public function testRejectsNegativeSolarPower(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Household(-1.0, 5.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler);
    }

    public function testRejectsNegativeBatteryCapacity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Household(3.0, -1.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler);
    }
}
