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
use App\Domain\Finance\Renovation;
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

    public function testCashPurchaseDebitsTheSavings(): void
    {
        $result = new RenovationHandler()->order(self::bareState(), Renovation::SolarPanels, RenovationHandler::FINANCING_CASH);

        self::assertInstanceOf(GameState::class, $result);
        self::assertSame(3.0, $result->household->solarKwc);
        self::assertSame(500_00, $result->savings->cents, '8000 − 7500 of panels.');
        self::assertFalse($result->loan->isActive());
        self::assertSame(0, $result->currentDay, 'Deciding does not advance the day.');
    }

    public function testCashIsRefusedWhenSavingsAreInsufficient(): void
    {
        $result = new RenovationHandler()->order(self::bareState(5000.0), Renovation::SolarPanels, RenovationHandler::FINANCING_CASH);

        self::assertIsString($result);
        self::assertStringContainsString('Épargne insuffisante', $result);
    }

    public function testLoanFinancesTheNetCostWithoutTouchingSavings(): void
    {
        $result = new RenovationHandler()->order(self::bareState(), Renovation::HeatPump, RenovationHandler::FINANCING_LOAN);

        self::assertInstanceOf(GameState::class, $result);
        self::assertSame(HeatingSystem::HeatPump, $result->household->heatingSystem);
        self::assertSame(8000_00, $result->savings->cents, 'Savings untouched.');
        self::assertSame(7800_00, $result->loan->remaining->cents, 'Net cost (13000 − 5200 prime) borrowed.');
    }

    public function testLoanIsRefusedForProductionEquipment(): void
    {
        $result = new RenovationHandler()->order(self::bareState(), Renovation::SolarPanels, RenovationHandler::FINANCING_LOAN);

        self::assertIsString($result);
        self::assertStringContainsString('éco-PTZ', $result);
    }

    public function testUnavailableWorkIsRefused(): void
    {
        $heatPumpHome = GameState::start(
            new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::HeatPump),
            Money::fromEuros(8000.0),
        );

        $result = new RenovationHandler()->order($heatPumpHome, Renovation::HeatPump, RenovationHandler::FINANCING_CASH);

        self::assertIsString($result);
    }

    public function testTheBrokenBoilerCanBeRepairedInCashWithTheStartingSavings(): void
    {
        $broken = GameState::start(
            new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::FuelOilBoiler, boilerBroken: true),
            Money::fromEuros(7750.0), // The recalibrated scenario savings.
        );

        $result = new RenovationHandler()->order($broken, Renovation::BoilerRepair, RenovationHandler::FINANCING_CASH);

        self::assertInstanceOf(GameState::class, $result);
        self::assertFalse($result->household->boilerBroken);
        self::assertSame(6250_00, $result->savings->cents, '7750 − 1500 of repair.');
    }

    public function testTheRepairCannotBeFinancedWithTheLoan(): void
    {
        $broken = GameState::start(
            new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::FuelOilBoiler, boilerBroken: true),
            Money::fromEuros(4000.0),
        );

        $result = new RenovationHandler()->order($broken, Renovation::BoilerRepair, RenovationHandler::FINANCING_LOAN);

        self::assertIsString($result);
        self::assertStringContainsString('éco-PTZ', $result);
    }

    public function testFullRenovationFitsUnderTheLoanCap(): void
    {
        // Chains every loan-eligible work (the 4 surface works + heat pump)
        // through the loan to exercise the cap mechanism end to end.
        $handler = new RenovationHandler();
        $state = self::bareState();

        foreach ([Renovation::RoofInsulation, Renovation::WallInsulationInterior, Renovation::Glazing, Renovation::HeatPump] as $work) {
            $result = $handler->order($state, $work, RenovationHandler::FINANCING_LOAN);
            self::assertInstanceOf(GameState::class, $result);
            $state = $result;
        }

        self::assertTrue($state->household->envelope->roofInsulated);
        self::assertSame(WallInsulation::Interior, $state->household->envelope->walls);
        self::assertSame(Glazing::Double, $state->household->envelope->glazing);
        self::assertSame(HeatingSystem::HeatPump, $state->household->heatingSystem);
        // Net costs at the "intermédiaire" 40 % rate: 2400 (roof) + 5400 (ITI)
        // + 4800 (glazing) + 7800 (heat pump) = 20 400 €, comfortably under the
        // 50 000 € éco-PTZ cap.
        self::assertSame(20400_00, $state->loan->borrowedTotal->cents);
    }

    public function testLowTempEmittersAndPelletBoilerAreFinanceableWithTheLoan(): void
    {
        $handler = new RenovationHandler();
        $state = self::bareState();

        $withEmitters = $handler->order($state, Renovation::LowTempEmitters, RenovationHandler::FINANCING_LOAN);
        self::assertInstanceOf(GameState::class, $withEmitters);
        self::assertTrue($withEmitters->household->lowTempEmitters);
        self::assertSame(3900_00, $withEmitters->loan->borrowedTotal->cents, 'Net cost (6500 − 2600 prime) borrowed.');

        $withPellet = $handler->order($withEmitters, Renovation::PelletBoiler, RenovationHandler::FINANCING_LOAN);
        self::assertInstanceOf(GameState::class, $withPellet);
        self::assertSame(HeatingSystem::PelletBoiler, $withPellet->household->heatingSystem);
        // 3900 (emitters) + 8400 (14000 − 5600 prime, pellet boiler) = 12 300 €.
        self::assertSame(12300_00, $withPellet->loan->borrowedTotal->cents);
    }
}
