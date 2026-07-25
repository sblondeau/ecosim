<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\RenovationHandler;
use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use App\Domain\Finance\Money;
use App\Domain\Simulation\GameState;
use PHPUnit\Framework\TestCase;

final class RenovationHandlerTest extends TestCase
{
    private static function bareState(float $savingsEuros = 8000.0): GameState
    {
        return GameState::start(
            new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::FuelOilBoiler),
            Money::fromEuros($savingsEuros),
        );
    }

    public function testCashPurchaseDebitsTheSavingsNowButSchedulesTheChantier(): void
    {
        $result = new RenovationHandler()->order(self::bareState(), 'solar_panels', RenovationHandler::FINANCING_CASH);

        self::assertInstanceOf(GameState::class, $result);
        self::assertSame(0.0, $result->household->solarKwc, 'The household is untouched at order — the chantier is scheduled.');
        self::assertSame(500_00, $result->savings->cents, 'Money is committed now: 8000 − 7500 of panels.');
        self::assertFalse($result->loan->isActive());
        self::assertSame(0, $result->currentDay, 'Ordering does not advance the day.');
        self::assertCount(1, $result->scheduledWorks);
        self::assertSame('solar_panels', $result->scheduledWorks[0]->workSlug);
        self::assertLessThan($result->scheduledWorks[0]->completionDay, $result->scheduledWorks[0]->chantierStartDay, 'The chantier window: start before completion.');
    }

    public function testAChantierAlreadyInProgressCannotBeReordered(): void
    {
        $handler = new RenovationHandler();
        $ordered = $handler->order(self::bareState(), 'solar_panels', RenovationHandler::FINANCING_CASH);
        self::assertInstanceOf(GameState::class, $ordered);

        $again = $handler->order($ordered, 'solar_panels', RenovationHandler::FINANCING_CASH);
        self::assertIsString($again, 'A work whose chantier is already scheduled cannot be ordered twice.');
        self::assertStringContainsString('déjà en cours', $again);
    }

    public function testASecondHeatingGeneratorCannotBeOrderedWhileOneIsInProgress(): void
    {
        $handler = new RenovationHandler();
        $withPacPending = $handler->order(self::bareState(), 'heat_pump', RenovationHandler::FINANCING_LOAN);
        self::assertInstanceOf(GameState::class, $withPacPending);

        // A pellet boiler conflicts with the pending heat-pump chantier: you do
        // not queue two generators.
        $refused = $handler->order($withPacPending, 'pellet_boiler', RenovationHandler::FINANCING_LOAN);
        self::assertIsString($refused);
        self::assertStringContainsString('chauffage', $refused);

        // Low-temp emitters share the heating slot but are not a generator —
        // still orderable alongside the pending heat pump.
        $emitters = $handler->order($withPacPending, 'low_temp_emitters', RenovationHandler::FINANCING_LOAN);
        self::assertInstanceOf(GameState::class, $emitters);
    }

    public function testCashIsRefusedWhenSavingsAreInsufficient(): void
    {
        $result = new RenovationHandler()->order(self::bareState(5000.0), 'solar_panels', RenovationHandler::FINANCING_CASH);

        self::assertIsString($result);
        self::assertStringContainsString('Épargne insuffisante', $result);
    }

    public function testLoanFinancesTheNetCostNowButSchedulesTheChantier(): void
    {
        $result = new RenovationHandler()->order(self::bareState(), 'heat_pump', RenovationHandler::FINANCING_LOAN);

        self::assertInstanceOf(GameState::class, $result);
        self::assertSame(HeatingSystem::FuelOilBoiler, $result->household->heatingSystem, 'Heating is untouched at order — the chantier is scheduled.');
        self::assertSame(8000_00, $result->savings->cents, 'Savings untouched.');
        self::assertSame(7800_00, $result->loan->remaining->cents, 'Net cost (13000 − 5200 prime) borrowed now.');
        self::assertCount(1, $result->scheduledWorks);
        self::assertSame('heat_pump', $result->scheduledWorks[0]->workSlug);
    }

    public function testLoanIsRefusedForProductionEquipment(): void
    {
        $result = new RenovationHandler()->order(self::bareState(), 'solar_panels', RenovationHandler::FINANCING_LOAN);

        self::assertIsString($result);
        self::assertStringContainsString('éco-PTZ', $result);
    }

    public function testUnavailableWorkIsRefused(): void
    {
        $heatPumpHome = GameState::start(
            new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::HeatPump),
            Money::fromEuros(8000.0),
        );

        $result = new RenovationHandler()->order($heatPumpHome, 'heat_pump', RenovationHandler::FINANCING_CASH);

        self::assertIsString($result);
    }

    public function testRefusesAnUnknownWorkSlug(): void
    {
        $result = new RenovationHandler()->order(self::bareState(), 'nope', RenovationHandler::FINANCING_CASH);

        self::assertIsString($result, 'an unknown slug is refused, never fatal');
    }

    public function testTheBrokenBoilerCanBeRepairedInCashWithTheStartingSavings(): void
    {
        $broken = GameState::start(
            new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::FuelOilBoiler, boilerBroken: true),
            Money::fromEuros(7750.0), // The recalibrated scenario savings.
        );

        $result = new RenovationHandler()->order($broken, 'boiler_repair', RenovationHandler::FINANCING_CASH);

        self::assertInstanceOf(GameState::class, $result);
        self::assertTrue($result->household->boilerBroken, 'Still broken at order — the repair chantier is scheduled (fast, but not instant).');
        self::assertSame(6250_00, $result->savings->cents, '7750 − 1500 of repair, committed now.');
        self::assertCount(1, $result->scheduledWorks);
        self::assertSame('boiler_repair', $result->scheduledWorks[0]->workSlug);
    }

    public function testTheRepairCannotBeFinancedWithTheLoan(): void
    {
        $broken = GameState::start(
            new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::FuelOilBoiler, boilerBroken: true),
            Money::fromEuros(4000.0),
        );

        $result = new RenovationHandler()->order($broken, 'boiler_repair', RenovationHandler::FINANCING_LOAN);

        self::assertIsString($result);
        self::assertStringContainsString('éco-PTZ', $result);
    }

    public function testFullRenovationFitsUnderTheLoanCap(): void
    {
        // Chains every loan-eligible work (the 4 surface works + heat pump)
        // through the loan to exercise the cap mechanism end to end.
        $handler = new RenovationHandler();
        $state = self::bareState();

        foreach (['roof_insulation', 'wall_insulation_interior', 'glazing', 'heat_pump'] as $work) {
            $result = $handler->order($state, $work, RenovationHandler::FINANCING_LOAN);
            self::assertInstanceOf(GameState::class, $result);
            $state = $result;
        }

        self::assertCount(4, $state->scheduledWorks, 'The four loan-financed chantiers are all scheduled.');
        // Net costs at the "intermédiaire" 40 % rate: 2400 (roof) + 5400 (ITI)
        // + 4800 (glazing) + 7800 (heat pump) = 20 400 €, comfortably under the
        // 50 000 € éco-PTZ cap — borrowed at order time.
        self::assertSame(20400_00, $state->loan->borrowedTotal->cents);
    }

    public function testLowTempEmittersAndPelletBoilerAreFinanceableWithTheLoan(): void
    {
        $handler = new RenovationHandler();
        $state = self::bareState();

        $withEmitters = $handler->order($state, 'low_temp_emitters', RenovationHandler::FINANCING_LOAN);
        self::assertInstanceOf(GameState::class, $withEmitters);
        self::assertSame(3900_00, $withEmitters->loan->borrowedTotal->cents, 'Net cost (6500 − 2600 prime) borrowed now.');

        $withPellet = $handler->order($withEmitters, 'pellet_boiler', RenovationHandler::FINANCING_LOAN);
        self::assertInstanceOf(GameState::class, $withPellet);
        self::assertCount(2, $withPellet->scheduledWorks, 'Both chantiers scheduled.');
        // 3900 (emitters) + 8400 (14000 − 5600 prime, pellet boiler) = 12 300 €.
        self::assertSame(12300_00, $withPellet->loan->borrowedTotal->cents);
    }
}
