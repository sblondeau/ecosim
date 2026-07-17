<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use App\Domain\Building\WaterHeater;
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

    public function testLowTempEmittersDefaultToFalseAndCanBeSet(): void
    {
        $household = new Household(0.0, 0.0, $this->original(), HeatingSystem::FuelOilBoiler);
        self::assertFalse($household->lowTempEmitters, 'Default: original high-temperature radiators.');

        $upgraded = $household->withLowTempEmitters(true);
        self::assertTrue($upgraded->lowTempEmitters);
        self::assertFalse($household->lowTempEmitters, 'original untouched');
    }

    public function testChangingHeatingSystemPreservesTheEmitters(): void
    {
        $lowTemp = new Household(0.0, 0.0, $this->original(), HeatingSystem::FuelOilBoiler, lowTempEmitters: true);

        $heatPumpHome = $lowTemp->withHeatingSystem(HeatingSystem::HeatPump);

        self::assertTrue($heatPumpHome->lowTempEmitters, 'Emitters are plumbing, not the generator — they stay when the boiler is swapped.');
    }

    public function testOtherWithersCarryTheLowTempEmittersFlag(): void
    {
        $lowTemp = new Household(0.0, 0.0, $this->original(), HeatingSystem::FuelOilBoiler, lowTempEmitters: true);

        self::assertTrue($lowTemp->withSolarKwc(3.0)->lowTempEmitters);
        self::assertTrue($lowTemp->withBatteryKwh(5.0)->lowTempEmitters);
        self::assertTrue($lowTemp->withEnvelope(new EnvelopeState(true, WallInsulation::Interior, Glazing::Double))->lowTempEmitters);
        self::assertTrue($lowTemp->withBoilerBroken(true)->lowTempEmitters);
        self::assertTrue($lowTemp->withHeatingSetpointC(21.0)->lowTempEmitters);
    }

    public function testWaterHeaterDefaultsToElectricTankAndCanBeSet(): void
    {
        $household = new Household(0.0, 0.0, $this->original(), HeatingSystem::FuelOilBoiler);
        self::assertSame(WaterHeater::ElectricTank, $household->waterHeater, 'Default: electric tank, baked into the base demand.');

        $thermo = $household->withWaterHeater(WaterHeater::Thermodynamic);
        self::assertSame(WaterHeater::Thermodynamic, $thermo->waterHeater);
        self::assertSame(WaterHeater::ElectricTank, $household->waterHeater, 'original untouched');
    }

    public function testOtherWithersCarryTheWaterHeaterChoice(): void
    {
        $thermo = new Household(0.0, 0.0, $this->original(), HeatingSystem::FuelOilBoiler, waterHeater: WaterHeater::Thermodynamic);

        self::assertSame(WaterHeater::Thermodynamic, $thermo->withSolarKwc(3.0)->waterHeater);
        self::assertSame(WaterHeater::Thermodynamic, $thermo->withBatteryKwh(5.0)->waterHeater);
        self::assertSame(WaterHeater::Thermodynamic, $thermo->withEnvelope(new EnvelopeState(true, WallInsulation::Interior, Glazing::Double))->waterHeater);
        self::assertSame(WaterHeater::Thermodynamic, $thermo->withHeatingSystem(HeatingSystem::HeatPump)->waterHeater, 'Replacing the heating system does not touch the water heater.');
        self::assertSame(WaterHeater::Thermodynamic, $thermo->withBoilerBroken(false)->waterHeater);
        self::assertSame(WaterHeater::Thermodynamic, $thermo->withHeatingSetpointC(21.0)->waterHeater);
        self::assertSame(WaterHeater::Thermodynamic, $thermo->withLowTempEmitters(true)->waterHeater);
    }
}
