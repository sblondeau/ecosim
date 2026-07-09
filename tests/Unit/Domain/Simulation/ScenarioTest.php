<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Simulation;

use App\Domain\Building\HeatingSystem;
use App\Domain\Building\InsulationLevel;
use App\Domain\Finance\Renovation;
use App\Domain\Finance\RenovationQuoter;
use App\Domain\Simulation\Scenario;
use PHPUnit\Framework\TestCase;

final class ScenarioTest extends TestCase
{
    public function testTheLockedScenarioStartsBareOnFuelOil(): void
    {
        $household = new Scenario()->initialHousehold();

        self::assertSame(0.0, $household->solarKwc);
        self::assertSame(0.0, $household->batteryKwh);
        self::assertSame(InsulationLevel::Original, $household->insulation);
        self::assertSame(HeatingSystem::FuelOilBoiler, $household->heatingSystem);
        self::assertFalse($household->boilerBroken, 'The boiler still runs on day 0 — the breakdown is scripted later.');
        self::assertSame('G', $household->dpeClass()->label());
    }

    public function testTheInitialStateCarriesTheCalibratedSavings(): void
    {
        $state = new Scenario()->initialState();

        self::assertSame(0, $state->currentDay);
        self::assertSame(7750_00, $state->savings->cents);
        self::assertFalse($state->loan->isActive());
    }

    public function testTheHeatPumpIsNotCashAffordableOnDayOne(): void
    {
        $scenario = new Scenario();
        $quote = new RenovationQuoter()->quote(Renovation::HeatPump, $scenario->initialHousehold());

        self::assertNotNull($quote);
        self::assertLessThan(
            $quote->netCost()->cents,
            $scenario->startingSavings()->cents,
            'Anticipating the switch takes the loan; the cash option opens just around the breakdown (balance decision).',
        );
    }

    public function testTheBreakdownFallsInsideTheHorizon(): void
    {
        self::assertLessThan(
            Scenario::HORIZON_DAYS,
            Scenario::BOILER_BREAKDOWN_DAY,
            'A scripted event beyond the horizon would never happen.',
        );
    }
}
