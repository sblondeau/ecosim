<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Finance\Renovation;
use App\Domain\Finance\RenovationQuoter;
use App\Domain\Simulation\GameState;

use function sprintf;

/**
 * Applies a player's renovation order to the game state: re-quotes the work
 * server-side (never trust displayed prices), enforces the financing rules,
 * and returns either the new state or a player-facing refusal.
 *
 * Rules mirror the real schemes: cash requires sufficient savings (no bank
 * lets you wire money you do not have), the zero-interest loan only covers
 * energy-performance works (insulation, heat pump) within its 50 000 € cap.
 */
final readonly class RenovationHandler
{
    public const string FINANCING_CASH = 'cash';
    public const string FINANCING_LOAN = 'loan';

    public function __construct(
        private RenovationQuoter $quoter = new RenovationQuoter(),
        private FinanceCalibration $finance = new FinanceCalibration(),
    ) {
    }

    /**
     * @return GameState|string the renovated state, or a French refusal message
     */
    public function order(GameState $state, Renovation $work, string $financing): GameState|string
    {
        $quote = $this->quoter->quote($work, $state->household);
        if (null === $quote) {
            return 'Ces travaux ne sont pas (ou plus) disponibles.';
        }

        $net = $quote->netCost();

        if (self::FINANCING_LOAN === $financing) {
            if (!$work->isLoanEligible()) {
                return 'L\'éco-PTZ ne finance que les travaux de performance énergétique (isolation, pompe à chaleur).';
            }

            $cap = Money::fromEuros($this->finance->loanCap()->value);
            if ($state->loan->borrowedTotal->plus($net)->cents > $cap->cents) {
                return sprintf('Plafond de l\'éco-PTZ dépassé (%s au total).', $cap->format());
            }

            return $state->renovated($quote->resultingHousehold, $state->savings, $state->loan->borrow($net));
        }

        if ($state->savings->cents < $net->cents) {
            return sprintf('Épargne insuffisante pour payer comptant (%s nécessaires).', $net->format());
        }

        return $state->renovated($quote->resultingHousehold, $state->savings->minus($net), $state->loan);
    }
}
