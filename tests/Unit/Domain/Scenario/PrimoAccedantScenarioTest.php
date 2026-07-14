<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Scenario;

use App\Domain\Building\HeatingSystem;
use App\Domain\Building\InsulationLevel;
use App\Domain\Finance\Renovation;
use App\Domain\Finance\RenovationQuoter;
use App\Domain\Scenario\BoilerBreakdownEvent;
use App\Domain\Scenario\PrimoAccedantScenario;
use PHPUnit\Framework\TestCase;

final class PrimoAccedantScenarioTest extends TestCase
{
    public function testTheLockedScenarioStartsBareOnFuelOil(): void
    {
        $household = new PrimoAccedantScenario()->initialHousehold();

        self::assertSame(0.0, $household->solarKwc);
        self::assertSame(0.0, $household->batteryKwh);
        self::assertSame(InsulationLevel::Original, $household->insulation);
        self::assertSame(HeatingSystem::FuelOilBoiler, $household->heatingSystem);
        self::assertFalse($household->boilerBroken, 'The boiler still runs on day 0 — the breakdown is scripted later.');
    }

    public function testTheInitialStateCarriesTheCalibratedSavings(): void
    {
        $state = new PrimoAccedantScenario()->initialState();

        self::assertSame(0, $state->currentDay);
        self::assertSame(7750_00, $state->savings->cents);
        self::assertFalse($state->loan->isActive());
    }

    public function testTheHeatPumpIsNotCashAffordableOnDayOne(): void
    {
        $scenario = new PrimoAccedantScenario();
        $quote = new RenovationQuoter()->quote(Renovation::HeatPump, $scenario->initialHousehold());

        self::assertNotNull($quote);
        self::assertLessThan(
            $quote->netCost()->cents,
            $scenario->startingSavings()->cents,
            'Anticipating the switch takes the loan; the cash option opens just around the breakdown (balance decision).',
        );
    }

    public function testTheScenarioScriptsTheBoilerBreakdownInsideTheHorizon(): void
    {
        $scenario = new PrimoAccedantScenario();

        self::assertCount(1, $scenario->events(), 'One scripted event in Phase 0-1 (§15).');
        self::assertInstanceOf(BoilerBreakdownEvent::class, $scenario->events()[0]);
        self::assertLessThan(
            $scenario->horizonDays(),
            PrimoAccedantScenario::BOILER_BREAKDOWN_DAY,
            'A scripted event beyond the horizon would never happen.',
        );
    }
}
