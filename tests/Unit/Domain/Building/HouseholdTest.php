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

    public function testOnlyTheFuelOilBoilerCanBeBroken(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::HeatPump, boilerBroken: true);
    }

    public function testReplacingTheHeatingSystemDiscardsTheBrokenBoiler(): void
    {
        $broken = new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler, boilerBroken: true);

        $heatPumpHome = $broken->withHeatingSystem(HeatingSystem::HeatPump);

        self::assertFalse($heatPumpHome->boilerBroken, 'The dead boiler left with the old system.');
    }

    public function testOtherWithersCarryTheBrokenFlag(): void
    {
        $broken = new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler, boilerBroken: true);

        self::assertTrue($broken->withSolarKwc(3.0)->boilerBroken, 'Installing panels does not fix the boiler.');
        self::assertTrue($broken->withInsulation(InsulationLevel::Retrofitted)->boilerBroken);
        self::assertFalse($broken->withBoilerBroken(false)->boilerBroken, 'The repair clears the flag.');
    }
}
