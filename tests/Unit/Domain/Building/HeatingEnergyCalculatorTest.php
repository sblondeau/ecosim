<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\HeatingEnergyCalculator;
use App\Domain\Building\HeatingSystem;
use App\Domain\Energy\EnergyCalibration;
use PHPUnit\Framework\TestCase;

final class HeatingEnergyCalculatorTest extends TestCase
{
    public function testZeroNeedConsumesNothing(): void
    {
        $calculator = new HeatingEnergyCalculator();

        $consumption = $calculator->consumptionFor(HeatingSystem::HeatPump, 0.0);

        self::assertSame(0.0, $consumption->electricityKwh);
        self::assertSame(0.0, $consumption->fuelOilLitres);
    }

    public function testHeatPumpDrawsElectricityDividedByScop(): void
    {
        $consumption = new HeatingEnergyCalculator()->consumptionFor(HeatingSystem::HeatPump, 70.0);

        // 70 kWh of heat / SCOP 2.5 (default: high-temp emitters) = 28 kWh of electricity, no fuel oil.
        self::assertSame(28.0, $consumption->electricityKwh);
        self::assertSame(0.0, $consumption->fuelOilLitres);
    }

    public function testHeatPumpOnOldRadiatorsUsesDegradedScop(): void
    {
        // 430 kWh need ÷ SCOP 2,5 = 172,0 kWh elec
        $c = new HeatingEnergyCalculator()->consumptionFor(HeatingSystem::HeatPump, 430.0, false);
        self::assertSame(172.0, $c->electricityKwh);
    }

    public function testHeatPumpWithLowTempEmittersUsesNominalScop(): void
    {
        // 430 kWh ÷ SCOP 4,3 = 100,0 kWh elec
        $c = new HeatingEnergyCalculator()->consumptionFor(HeatingSystem::HeatPump, 430.0, true);
        self::assertSame(100.0, $c->electricityKwh);
    }

    public function testBoilerBurnsMoreEnergyThanTheHeatItDelivers(): void
    {
        $calibration = new EnergyCalibration();
        $consumption = new HeatingEnergyCalculator($calibration)->consumptionFor(HeatingSystem::FuelOilBoiler, 100.0);

        self::assertSame(0.0, $consumption->electricityKwh);
        $burntKwh = $consumption->fuelOilLitres * $calibration->fuelOilEnergyKwhPerLitre()->value;
        self::assertGreaterThan(100.0, $burntKwh, 'Boiler losses: fuel energy exceeds delivered heat.');
    }

    public function testElectrificationLessonHeatPumpBeatsBoilerOnFinalEnergy(): void
    {
        $calibration = new EnergyCalibration();
        $calculator = new HeatingEnergyCalculator($calibration);
        $need = 100.0;

        $boiler = $calculator->consumptionFor(HeatingSystem::FuelOilBoiler, $need);
        // The 3-4x lesson holds at the heat pump's nominal operating point:
        // low-temperature emitters (game-design §12).
        $heatPump = $calculator->consumptionFor(HeatingSystem::HeatPump, $need, true);

        $boilerFinalKwh = $boiler->fuelOilLitres * $calibration->fuelOilEnergyKwhPerLitre()->value;

        // Game-design §12: ~3-4x less final energy for the same delivered heat.
        self::assertGreaterThan(3.0, $boilerFinalKwh / $heatPump->electricityKwh);
    }

    public function testPelletBoilerBurnsKilograms(): void
    {
        // 414 kWh ÷ 0,90 rendement ÷ 4,6 kWh/kg = 100,0 kg
        $c = new HeatingEnergyCalculator()->consumptionFor(HeatingSystem::PelletBoiler, 414.0);
        self::assertSame(100.0, $c->pelletKg);
        self::assertSame(0.0, $c->fuelOilLitres);
        self::assertSame(0.0, $c->electricityKwh);
    }

    public function testOldRadiatorsShrinkButDoNotEraseTheElectrificationLesson(): void
    {
        // "A heat pump is a system, not a box": on the original high-temperature
        // emitters, the SCOP degrades to 2.5 — still better than the boiler,
        // but the flagship 3-4x margin is gone (game-design §12, arbre travaux T4).
        $calibration = new EnergyCalibration();
        $calculator = new HeatingEnergyCalculator($calibration);
        $need = 100.0;

        $boiler = $calculator->consumptionFor(HeatingSystem::FuelOilBoiler, $need);
        $heatPump = $calculator->consumptionFor(HeatingSystem::HeatPump, $need, false);

        $boilerFinalKwh = $boiler->fuelOilLitres * $calibration->fuelOilEnergyKwhPerLitre()->value;
        $ratio = $boilerFinalKwh / $heatPump->electricityKwh;

        self::assertGreaterThan(1.0, $ratio, 'Still less final energy than the boiler.');
        self::assertLessThan(3.0, $ratio, 'But the nominal 3-4x margin requires low-temp emitters.');
    }
}
