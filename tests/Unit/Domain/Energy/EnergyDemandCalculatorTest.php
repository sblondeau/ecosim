<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Energy;

use App\Domain\Building\WaterHeater;
use App\Domain\Energy\EnergyDemandCalculator;
use App\Domain\Time\GameDate;

use function array_unique;
use function count;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EnergyDemandCalculatorTest extends TestCase
{
    private const int SEED = 2025;

    private static function date(string $date): GameDate
    {
        $epoch = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        self::assertInstanceOf(DateTimeImmutable::class, $epoch);

        return GameDate::epoch($epoch);
    }

    public function testDemandIsPositive(): void
    {
        $calculator = new EnergyDemandCalculator();

        self::assertGreaterThan(0.0, $calculator->dailyDemandKwh(self::SEED, self::date('2025-05-01')));
    }

    public function testIsDeterministicForTheSameSeedAndDay(): void
    {
        $calculator = new EnergyDemandCalculator();
        $date = self::date('2025-03-10');

        self::assertSame(
            $calculator->dailyDemandKwh(self::SEED, $date),
            $calculator->dailyDemandKwh(self::SEED, $date),
        );
    }

    public function testDemandVariesFromDayToDay(): void
    {
        $calculator = new EnergyDemandCalculator();

        $date = self::date('2025-05-01');
        $values = [];
        for ($i = 0; $i < 7; ++$i) {
            $values[] = $calculator->dailyDemandKwh(self::SEED, $date);
            $date = $date->next();
        }

        self::assertGreaterThan(1, count(array_unique($values)), 'Seeded noise: no two identical days in a week.');
    }

    public function testWinterDemandExceedsSummerDemandOnAverage(): void
    {
        $calculator = new EnergyDemandCalculator();

        self::assertGreaterThan(
            $this->averageOver($calculator, self::date('2025-07-01'), 31),
            $this->averageOver($calculator, self::date('2025-01-01'), 31),
        );
    }

    private function averageOver(EnergyDemandCalculator $calculator, GameDate $start, int $days): float
    {
        $sum = 0.0;
        $date = $start;
        for ($i = 0; $i < $days; ++$i) {
            $sum += $calculator->dailyDemandKwh(self::SEED, $date);
            $date = $date->next();
        }

        return $sum / $days;
    }

    public function testThermodynamicWaterHeaterCutsEcsElectricity(): void
    {
        $calc = new EnergyDemandCalculator();
        $date = GameDate::fromDayIndex(new DateTimeImmutable('2025-06-15'), 100);
        $electric = $calc->dailyDemandKwh(42, $date, WaterHeater::ElectricTank);
        $thermo = $calc->dailyDemandKwh(42, $date, WaterHeater::Thermodynamic);
        // économie = 2,5 × (1 − 1/3) = 1,666… → arrondi 2 décimales sur chaque terme
        self::assertEqualsWithDelta(1.67, $electric - $thermo, 0.02);
    }

    public function testElectricTankKeepsTheBaselineDemand(): void
    {
        // le ballon électrique NE change PAS la demande (référence à 10 kWh) → non régressif
        $calc = new EnergyDemandCalculator();
        $date = GameDate::fromDayIndex(new DateTimeImmutable('2025-06-15'), 100);
        self::assertSame(
            $calc->dailyDemandKwh(42, $date, WaterHeater::ElectricTank),
            $calc->dailyDemandKwh(42, $date), // défaut = ElectricTank
        );
    }
}
