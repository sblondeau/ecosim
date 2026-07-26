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
use App\Domain\Finance\RenovationCatalog;
use App\Domain\Simulation\GameState;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class RenovationHandlerTest extends TestCase
{
    private static function bareState(float $savingsEuros = 8000.0): GameState
    {
        return GameState::start(
            new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::FuelOilBoiler),
            Money::fromEuros($savingsEuros),
        );
    }

    /**
     * Mimics the engine posing every scheduled chantier: applies its effect to
     * the household and frees the crew. Used to chain professional works in a
     * test, since only one pro chantier may be in flight at a time.
     */
    private static function completed(GameState $state): GameState
    {
        $catalog = new RenovationCatalog();
        $household = $state->household;
        foreach ($state->scheduledWorks as $chantier) {
            $offer = $catalog->get($chantier->workSlug)->offerFor($household);
            if (null !== $offer) {
                $household = $offer->resultingHousehold;
            }
        }

        return $state->withHousehold($household)->withScheduledWorks([]);
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

    public function testOnlyOneProfessionalChantierRunsAtATime(): void
    {
        $handler = new RenovationHandler();
        $withPacPending = $handler->order(self::bareState(), 'heat_pump', RenovationHandler::FINANCING_LOAN);
        self::assertInstanceOf(GameState::class, $withPacPending);

        // Any second PROFESSIONAL work is refused while the PAC chantier is in
        // flight — not just another generator (that exclusivity is subsumed).
        foreach (['pellet_boiler', 'low_temp_emitters', 'roof_insulation'] as $blocked) {
            $refused = $handler->order($withPacPending, $blocked, RenovationHandler::FINANCING_LOAN);
            self::assertIsString($refused, sprintf('%s is a pro work, refused while a chantier runs.', $blocked));
            self::assertStringContainsString('déjà en cours', $refused);
        }

        // A self-doable geste runs in parallel with the pro chantier.
        $curtains = $handler->order($withPacPending, 'thermal_curtains', RenovationHandler::FINANCING_CASH);
        self::assertInstanceOf(GameState::class, $curtains);
    }

    public function testOrderingFrontsTheStickerPriceAndDefersTheSubsidyRefund(): void
    {
        // Roof insulation: 4000 sticker, 40 % prime → 1600, net 2400. Paid cash.
        $result = new RenovationHandler()->order(self::bareState(), 'roof_insulation', RenovationHandler::FINANCING_CASH);

        self::assertInstanceOf(GameState::class, $result);
        self::assertSame(4000_00, 8000_00 - $result->savings->cents, 'Savings front the full 4000 sticker, not the 2400 net.');
        self::assertCount(1, $result->pendingSubsidies, 'The 1600 prime is scheduled as a refund, not deducted now.');
        self::assertSame(1600_00, $result->pendingSubsidies[0]->amount->cents);
        self::assertSame($result->scheduledWorks[0]->completionDay + 60, $result->pendingSubsidies[0]->disbursementDay, 'The prime lands ~60 days after the pose.');
    }

    public function testLoanBorrowsTheStickerNotTheNet(): void
    {
        // Heat pump: 13000 sticker, 40 % prime → 5200, net 7800. Financed by the PTZ.
        $result = new RenovationHandler()->order(self::bareState(), 'heat_pump', RenovationHandler::FINANCING_LOAN);

        self::assertInstanceOf(GameState::class, $result);
        self::assertSame(13000_00, $result->loan->borrowedTotal->cents, 'The éco-PTZ fronts the full sticker; the prime refunds later.');
        self::assertCount(1, $result->pendingSubsidies);
        self::assertSame(5200_00, $result->pendingSubsidies[0]->amount->cents);
    }

    public function testAnEcoPtzThatWouldBreachThe35PercentDebtWallIsRefused(): void
    {
        $handler = new RenovationHandler();
        $state = self::bareState();

        // Pile up éco-PTZ (full stickers): ITE 18000 + PAC 13000 = 31 000 →
        // ratio still under 35 %. Each chantier posed before the next (1 crew).
        $state = self::completed($handler->order($state, 'wall_insulation_exterior', RenovationHandler::FINANCING_LOAN));
        $state = self::completed($handler->order($state, 'heat_pump', RenovationHandler::FINANCING_LOAN));

        // The next loan (roof, +4000 → 35 000 borrowed) tips the debt ratio past
        // 35 % — the bank refuses it, well before the 50 000 € plafond.
        $refused = $handler->order($state, 'roof_insulation', RenovationHandler::FINANCING_LOAN);
        self::assertIsString($refused);
        self::assertStringContainsString('endettement', $refused);

        // But paying that same work CASH is never gated by solvency.
        $cash = $handler->order($state, 'roof_insulation', RenovationHandler::FINANCING_CASH);
        self::assertInstanceOf(GameState::class, $cash, 'Cash is limited by savings, not the debt ratio.');
    }

    public function testCashIsRefusedWhenSavingsAreInsufficient(): void
    {
        $result = new RenovationHandler()->order(self::bareState(5000.0), 'solar_panels', RenovationHandler::FINANCING_CASH);

        self::assertIsString($result);
        self::assertStringContainsString('Épargne insuffisante', $result);
    }

    public function testLoanFrontsTheStickerNowAndSchedulesTheChantier(): void
    {
        $result = new RenovationHandler()->order(self::bareState(), 'heat_pump', RenovationHandler::FINANCING_LOAN);

        self::assertInstanceOf(GameState::class, $result);
        self::assertSame(HeatingSystem::FuelOilBoiler, $result->household->heatingSystem, 'Heating is untouched at order — the chantier is scheduled.');
        self::assertSame(8000_00, $result->savings->cents, 'Savings untouched (the PTZ fronts the cost).');
        self::assertSame(13000_00, $result->loan->remaining->cents, 'The PTZ borrows the full 13000 sticker; the 5200 prime refunds later.');
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

    public function testTheSolvencyWallCapsThePtzWellBeforeThe50kPlafond(): void
    {
        // Chains loan-eligible works: full stickers accumulate on the PTZ, but
        // the 35 % debt wall (§ contrainte ③) bites long before the 50 000 € cap.
        $handler = new RenovationHandler();
        $state = self::bareState();

        // 4000 (roof) + 9000 (ITI) + 8000 (glazing) = 21 000 € → ratio ~33 %, fits.
        foreach (['roof_insulation', 'wall_insulation_interior', 'glazing'] as $work) {
            $state = self::completed($handler->order($state, $work, RenovationHandler::FINANCING_LOAN));
        }
        self::assertSame(21000_00, $state->loan->borrowedTotal->cents);

        // Adding the heat pump (+13000 → 34000) would tip the ratio past 35 %,
        // so the PTZ is refused far under the 50 000 € plafond.
        $refused = $handler->order($state, 'heat_pump', RenovationHandler::FINANCING_LOAN);
        self::assertIsString($refused);
        self::assertStringContainsString('endettement', $refused);
    }

    public function testLowTempEmittersAndPelletBoilerAreFinanceableWithTheLoan(): void
    {
        $handler = new RenovationHandler();
        $state = self::bareState();

        $withEmitters = $handler->order($state, 'low_temp_emitters', RenovationHandler::FINANCING_LOAN);
        self::assertInstanceOf(GameState::class, $withEmitters);
        self::assertSame(6500_00, $withEmitters->loan->borrowedTotal->cents, 'The PTZ fronts the full 6500 sticker.');

        // Pose the emitters chantier first (one pro at a time), then the boiler.
        $withPellet = $handler->order(self::completed($withEmitters), 'pellet_boiler', RenovationHandler::FINANCING_LOAN);
        self::assertInstanceOf(GameState::class, $withPellet);
        // Full stickers: 6500 (emitters) + 14000 (pellet boiler) = 20 500 €.
        self::assertSame(20500_00, $withPellet->loan->borrowedTotal->cents);
    }
}
