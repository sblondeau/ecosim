<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\RenovationHandler;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
use App\Domain\Finance\Money;
use App\Domain\Finance\Renovation;
use App\Domain\Simulation\GameState;
use PHPUnit\Framework\TestCase;

final class RenovationHandlerTest extends TestCase
{
    private static function bareState(float $savingsEuros = 8000.0): GameState
    {
        return GameState::start(
            new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler),
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
            new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::HeatPump),
            Money::fromEuros(8000.0),
        );

        $result = new RenovationHandler()->order($heatPumpHome, Renovation::HeatPump, RenovationHandler::FINANCING_CASH);

        self::assertIsString($result);
    }

    public function testFullRenovationFitsUnderTheLoanCap(): void
    {
        $handler = new RenovationHandler();
        $state = self::bareState();

        foreach ([Renovation::Insulation, Renovation::Insulation, Renovation::HeatPump] as $work) {
            $result = $handler->order($state, $work, RenovationHandler::FINANCING_LOAN);
            self::assertInstanceOf(GameState::class, $result);
            $state = $result;
        }

        // Nets: 9000 + 15000 + 7800 = 31 800 € — under the 50 000 € cap.
        self::assertSame(31800_00, $state->loan->borrowedTotal->cents);
        self::assertSame(InsulationLevel::Reinforced, $state->household->insulation);
        self::assertSame(HeatingSystem::HeatPump, $state->household->heatingSystem);
    }
}
