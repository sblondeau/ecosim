<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Scenario;

use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\WallInsulation;
use App\Domain\Finance\RenovationQuoter;
use App\Domain\Finance\Work\HeatPumpWork;
use App\Domain\Scenario\BoilerBreakdownEvent;
use App\Domain\Scenario\PrimoAccedantScenario;
use App\Domain\Scenario\ScenarioIntroEvent;
use PHPUnit\Framework\TestCase;

final class PrimoAccedantScenarioTest extends TestCase
{
    public function testTheLockedScenarioStartsBareOnFuelOil(): void
    {
        $household = new PrimoAccedantScenario()->initialHousehold();

        self::assertSame(0.0, $household->solarKwc);
        self::assertSame(0.0, $household->batteryKwh);
        self::assertSame(HeatingSystem::FuelOilBoiler, $household->heatingSystem);
        self::assertFalse($household->boilerBroken, 'The boiler still runs on day 0 — the breakdown is scripted later.');
    }

    public function testHouseStartsUninsulated(): void
    {
        $household = new PrimoAccedantScenario()->initialHousehold();

        self::assertFalse($household->envelope->roofInsulated);
        self::assertSame(WallInsulation::None, $household->envelope->walls);
        self::assertSame(Glazing::Single, $household->envelope->glazing);
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
        $quote = new RenovationQuoter()->quote(new HeatPumpWork(), $scenario->initialHousehold());

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

    public function testExplainedEventsIncludeTheIntroAheadOfTheScriptedBreakdown(): void
    {
        $explained = new PrimoAccedantScenario()->explainedEvents();

        self::assertCount(2, $explained, 'The intro is not a ScriptedEvent, so it does not show up in events().');
        self::assertInstanceOf(ScenarioIntroEvent::class, $explained[0], 'The intro comes first: it is relevant from day 0.');
        self::assertInstanceOf(BoilerBreakdownEvent::class, $explained[1]);
    }
}
