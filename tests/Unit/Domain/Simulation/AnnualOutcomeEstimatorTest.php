<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Simulation;

use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
use App\Domain\Simulation\AnnualOutcomeEstimator;
use PHPUnit\Framework\TestCase;

final class AnnualOutcomeEstimatorTest extends TestCase
{
    private static function barePassoire(): Household
    {
        return new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler);
    }

    public function testTheEstimateIsDeterministic(): void
    {
        $estimator = new AnnualOutcomeEstimator();

        $a = $estimator->estimate(self::barePassoire());
        $b = $estimator->estimate(self::barePassoire());

        self::assertSame($a->netEnergyCost->cents, $b->netEnergyCost->cents);
        self::assertSame($a->averageComfortScore, $b->averageComfortScore);
    }

    public function testTheFuelOilPassoireCostsThousandsAYear(): void
    {
        $outcome = new AnnualOutcomeEstimator()->estimate(self::barePassoire());

        self::assertGreaterThan(3000_00, $outcome->netEnergyCost->cents, 'The passoire burns money.');
        self::assertSame(0.0, $outcome->productionKwh);
        self::assertSame(0.0, $outcome->selfSufficiencyRatio, 'Everything is bought from the grid.');
    }

    public function testTheRenovatedHeatPumpHomeIsFarCheaperToRun(): void
    {
        $estimator = new AnnualOutcomeEstimator();

        $passoire = $estimator->estimate(self::barePassoire());
        $renovated = $estimator->estimate(
            new Household(3.0, 5.0, InsulationLevel::Reinforced, HeatingSystem::HeatPump),
        );

        self::assertLessThan(
            $passoire->netEnergyCost->cents / 2,
            $renovated->netEnergyCost->cents,
            'Insulation + heat pump + solar at least halve the annual energy cost.',
        );
        self::assertGreaterThanOrEqual($passoire->averageComfortScore, $renovated->averageComfortScore);
    }

    public function testABatteryRaisesSelfSufficiencyOnTheSamePanels(): void
    {
        $estimator = new AnnualOutcomeEstimator();

        $panelsOnly = $estimator->estimate(new Household(3.0, 0.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler));
        $withBattery = $estimator->estimate(new Household(3.0, 5.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler));

        self::assertGreaterThan(
            $panelsOnly->selfSufficiencyRatio,
            $withBattery->selfSufficiencyRatio,
            'The battery moves daytime surplus into the evening.',
        );
        self::assertGreaterThan(0.0, $panelsOnly->productionKwh);
    }

    public function testABrokenBoilerYearIsCheapAndMiserable(): void
    {
        $estimator = new AnnualOutcomeEstimator();

        $running = $estimator->estimate(self::barePassoire());
        $broken = $estimator->estimate(self::barePassoire()->withBoilerBroken(true));

        self::assertLessThan($running->netEnergyCost->cents, $broken->netEnergyCost->cents, 'No fuel burnt.');
        self::assertLessThan($running->averageComfortScore, $broken->averageComfortScore, 'A freezing house.');
    }
}
