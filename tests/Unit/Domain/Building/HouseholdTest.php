<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HouseholdTest extends TestCase
{
    private function original(): EnvelopeState
    {
        return new EnvelopeState(false, WallInsulation::None, Glazing::Single);
    }

    public function testExposesItsConfiguration(): void
    {
        $household = new Household(3.0, 5.0, $this->original(), HeatingSystem::FuelOilBoiler);

        self::assertSame(3.0, $household->solarKwc);
        self::assertSame(5.0, $household->batteryKwh);
        self::assertEquals($this->original(), $household->envelope);
        self::assertSame(HeatingSystem::FuelOilBoiler, $household->heatingSystem);
    }

    public function testRejectsNegativeSolarPower(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Household(-1.0, 5.0, $this->original(), HeatingSystem::FuelOilBoiler);
    }

    public function testRejectsNegativeBatteryCapacity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Household(3.0, -1.0, $this->original(), HeatingSystem::FuelOilBoiler);
    }

    public function testOnlyTheFuelOilBoilerCanBeBroken(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Household(0.0, 0.0, $this->original(), HeatingSystem::HeatPump, boilerBroken: true);
    }

    public function testReplacingTheHeatingSystemDiscardsTheBrokenBoiler(): void
    {
        $broken = new Household(0.0, 0.0, $this->original(), HeatingSystem::FuelOilBoiler, boilerBroken: true);

        $heatPumpHome = $broken->withHeatingSystem(HeatingSystem::HeatPump);

        self::assertFalse($heatPumpHome->boilerBroken, 'The dead boiler left with the old system.');
    }

    public function testOtherWithersCarryTheBrokenFlag(): void
    {
        $broken = new Household(0.0, 0.0, $this->original(), HeatingSystem::FuelOilBoiler, boilerBroken: true);

        self::assertTrue($broken->withSolarKwc(3.0)->boilerBroken, 'Installing panels does not fix the boiler.');
        self::assertTrue($broken->withEnvelope(new EnvelopeState(true, WallInsulation::Interior, Glazing::Double))->boilerBroken);
        self::assertFalse($broken->withBoilerBroken(false)->boilerBroken, 'The repair clears the flag.');
    }

    public function testTheSetpointDefaultsToNineteenAndSurvivesOtherWithers(): void
    {
        $house = new Household(0.0, 0.0, $this->original(), HeatingSystem::FuelOilBoiler);
        self::assertSame(19.0, $house->heatingSetpointC, 'Default thermostat: 19 °C (R241-26).');

        $warmer = $house->withHeatingSetpointC(21.0);
        self::assertSame(21.0, $warmer->heatingSetpointC);
        self::assertSame(21.0, $warmer->withSolarKwc(3.0)->heatingSetpointC, 'Installing panels keeps the dialled setpoint.');
        self::assertSame(21.0, $warmer->withEnvelope(new EnvelopeState(true, WallInsulation::Exterior, Glazing::Triple))->heatingSetpointC);
        self::assertSame(21.0, $warmer->withHeatingSystem(HeatingSystem::HeatPump)->heatingSetpointC);
    }

    public function testWithEnvelopeReplacesEnvelopeOnly(): void
    {
        $household = new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::FuelOilBoiler);

        $renovated = $household->withEnvelope(new EnvelopeState(true, WallInsulation::Exterior, Glazing::Triple));

        self::assertSame(WallInsulation::None, $household->envelope->walls, 'original untouched');
        self::assertSame(WallInsulation::Exterior, $renovated->envelope->walls);
        self::assertSame(HeatingSystem::FuelOilBoiler, $renovated->heatingSystem);
    }
}
