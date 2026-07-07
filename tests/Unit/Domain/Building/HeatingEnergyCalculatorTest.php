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

        // 70 kWh of heat / SCOP 3.5 = 20 kWh of electricity, no fuel oil.
        self::assertSame(20.0, $consumption->electricityKwh);
        self::assertSame(0.0, $consumption->fuelOilLitres);
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
        $heatPump = $calculator->consumptionFor(HeatingSystem::HeatPump, $need);

        $boilerFinalKwh = $boiler->fuelOilLitres * $calibration->fuelOilEnergyKwhPerLitre()->value;

        // Game-design §12: ~3-4x less final energy for the same delivered heat.
        self::assertGreaterThan(3.0, $boilerFinalKwh / $heatPump->electricityKwh);
    }
}
