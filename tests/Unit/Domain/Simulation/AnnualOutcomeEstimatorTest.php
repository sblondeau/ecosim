<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Simulation;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use App\Domain\Simulation\AnnualOutcomeEstimator;
use PHPUnit\Framework\TestCase;

final class AnnualOutcomeEstimatorTest extends TestCase
{
    private static function original(): EnvelopeState
    {
        return new EnvelopeState(false, WallInsulation::None, Glazing::Single);
    }

    private static function bestEnvelope(): EnvelopeState
    {
        return new EnvelopeState(true, WallInsulation::Exterior, Glazing::Triple);
    }

    private static function barePassoire(): Household
    {
        return new Household(0.0, 0.0, self::original(), HeatingSystem::FuelOilBoiler);
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
            new Household(3.0, 5.0, self::bestEnvelope(), HeatingSystem::HeatPump),
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

        $panelsOnly = $estimator->estimate(new Household(3.0, 0.0, self::original(), HeatingSystem::FuelOilBoiler));
        $withBattery = $estimator->estimate(new Household(3.0, 5.0, self::original(), HeatingSystem::FuelOilBoiler));

        self::assertGreaterThan(
            $panelsOnly->selfSufficiencyRatio,
            $withBattery->selfSufficiencyRatio,
            'The battery moves daytime surplus into the evening.',
        );
        self::assertGreaterThan(0.0, $panelsOnly->productionKwh);
    }

    public function testAPelletHouseholdAccumulatesPelletKgOverTheYear(): void
    {
        $estimator = new AnnualOutcomeEstimator();

        $pelletHouse = $estimator->estimate(
            new Household(0.0, 0.0, self::original(), HeatingSystem::PelletBoiler),
        );

        self::assertGreaterThan(0.0, $pelletHouse->pelletKg, 'A passoire heated by pellets burns pellets all winter.');
        self::assertSame(0.0, $pelletHouse->fuelOilLitres, 'No fuel oil at all — the carrier is exclusive.');
    }

    public function testABrokenBoilerYearRunsOnRuinousEmergencyHeat(): void
    {
        $estimator = new AnnualOutcomeEstimator();

        $running = $estimator->estimate(self::barePassoire());
        $broken = $estimator->estimate(self::barePassoire()->withBoilerBroken(true));

        self::assertLessThan(
            $running->averageComfortScore,
            $broken->averageComfortScore,
            'Survival setpoint all year: clearly worse comfort.',
        );
        self::assertGreaterThan(
            $running->netEnergyCost->cents / 2,
            $broken->netEnergyCost->cents,
            'The emergency heaters keep the energy bill in the same painful league — freezing is never "profitable".',
        );
    }
}
