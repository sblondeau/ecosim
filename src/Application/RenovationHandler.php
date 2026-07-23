<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Finance\RenovationCatalog;
use App\Domain\Finance\RenovationDelays;
use App\Domain\Finance\RenovationQuoter;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\ScheduledWork;

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
        private RenovationCatalog $catalog = new RenovationCatalog(),
        private RenovationDelays $delays = new RenovationDelays(),
    ) {
    }

    /**
     * @return GameState|string the renovated state, or a French refusal message
     */
    public function order(GameState $state, string $workSlug, string $financing): GameState|string
    {
        $work = $this->catalog->tryGet($workSlug);
        if (null === $work) {
            return 'Ces travaux ne sont pas (ou plus) disponibles.';
        }

        $quote = $this->quoter->quote($work, $state->household);
        if (null === $quote) {
            return 'Ces travaux ne sont pas (ou plus) disponibles.';
        }

        foreach ($state->scheduledWorks as $pending) {
            if ($pending->workSlug === $workSlug) {
                return 'Ce chantier est déjà en cours.';
            }
        }

        $net = $quote->netCost();
        $chantier = $this->schedule($state, $workSlug, $financing);

        if (self::FINANCING_LOAN === $financing) {
            if (!$work->qualifiesForEnergyAid()) {
                return 'L\'éco-PTZ ne finance que les travaux de performance énergétique (isolation, pompe à chaleur).';
            }

            $cap = Money::fromEuros($this->finance->loanCap()->value);
            if ($state->loan->borrowedTotal->plus($net)->cents > $cap->cents) {
                return sprintf('Plafond de l\'éco-PTZ dépassé (%s au total).', $cap->format());
            }

            return $state->scheduling($state->savings, $state->loan->borrow($net), $chantier);
        }

        if ($state->savings->cents < $net->cents) {
            return sprintf('Épargne insuffisante pour payer comptant (%s nécessaires).', $net->format());
        }

        return $state->scheduling($state->savings->minus($net), $state->loan, $chantier);
    }

    /**
     * The chantier window for a work ordered on the current day: the artisan
     * arrives after the lead time, the work is done after the pose. When
     * financed by the éco-PTZ, the funds-release delay stacks BEFORE the lead —
     * so the loan is not mobilisable for an emergency (§ délais, la panne).
     */
    private function schedule(GameState $state, string $workSlug, string $financing): ScheduledWork
    {
        $delay = $this->delays->for($workSlug);
        $funds = self::FINANCING_LOAN === $financing ? $this->delays->ptzFundsReleaseDays() : 0;
        $start = $state->currentDay + $funds + $delay->leadDays;

        return new ScheduledWork($workSlug, $start, $start + $delay->buildDays);
    }
}
