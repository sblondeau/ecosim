<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Finance\PendingSubsidy;
use App\Domain\Finance\RenovationCatalog;
use App\Domain\Finance\RenovationConcurrency;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationQuote;
use App\Domain\Finance\RenovationQuoter;
use App\Domain\Finance\SolvencyPolicy;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\ScheduledWork;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
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
        private RenovationConcurrency $concurrency = new RenovationConcurrency(),
        private SolvencyPolicy $solvency = new SolvencyPolicy(),
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

        $inProgressSlugs = array_map(static fn (ScheduledWork $pending): string => $pending->workSlug, $state->scheduledWorks);
        if (in_array($workSlug, $inProgressSlugs, true)) {
            return 'Ce chantier est déjà en cours.';
        }
        if (!$this->concurrency->allowsOrdering($work, $this->inProgressWorks($state))) {
            return 'Un chantier est déjà en cours — attendez sa pose avant d\'en commander un autre.';
        }

        // The household fronts the FULL sticker (§ contrainte ②); the prime is
        // paid back later, ~60 days after the pose (MaPrimeRénov' après travaux).
        $cost = $quote->cost;
        $chantier = $this->schedule($state, $work, $financing);
        $refund = $this->deferredSubsidy($quote, $chantier, self::FINANCING_LOAN === $financing);

        if (self::FINANCING_LOAN === $financing) {
            if (!$work->qualifiesForEnergyAid()) {
                return 'L\'éco-PTZ ne finance que les travaux de performance énergétique (isolation, pompe à chaleur).';
            }

            $cap = Money::fromEuros($this->finance->loanCap()->value);
            if ($state->loan->borrowedTotal->plus($cost)->cents > $cap->cents) {
                return sprintf('Plafond de l\'éco-PTZ dépassé (%s au total).', $cap->format());
            }

            // The bank checks solvency (§ contrainte ③): the éco-PTZ counts in
            // the debt ratio, so a household near the 35 % wall cannot pile it on.
            if (!$this->solvency->allowsBorrowing($state->loan, $cost)) {
                return sprintf(
                    'Prêt refusé : ce crédit porterait votre taux d\'endettement à %d %% (plafond %d %%).',
                    (int) round($this->solvency->debtRatioAfterBorrowing($state->loan, $cost) * 100),
                    (int) round($this->solvency->ceiling() * 100),
                );
            }

            return $state->scheduling($state->savings, $state->loan->borrow($cost), $chantier, $refund);
        }

        if ($state->savings->cents < $cost->cents) {
            return sprintf('Épargne insuffisante pour avancer les travaux (%s à régler, prime remboursée après).', $cost->format());
        }

        return $state->scheduling($state->savings->minus($cost), $state->loan, $chantier, $refund);
    }

    /**
     * The prime owed on this work, scheduled to land ~60 days after the pose
     * (§ contrainte ②). Null when the work carries no subsidy (solar, battery,
     * repair) — nothing to defer.
     */
    private function deferredSubsidy(RenovationQuote $quote, ScheduledWork $chantier, bool $repaysLoan): ?PendingSubsidy
    {
        if ($quote->subsidy->cents <= 0) {
            return null;
        }

        $disbursementDay = $chantier->completionDay + (int) $this->finance->subsidyDisbursementDays()->value;

        return new PendingSubsidy($quote->subsidy, max(0, $disbursementDay), $repaysLoan);
    }

    /**
     * The chantier window for a work ordered on the current day: the artisan
     * arrives after the lead time, the work is done after the pose. When
     * financed by the éco-PTZ, the funds-release delay stacks BEFORE the lead —
     * so the loan is not mobilisable for an emergency (§ délais, la panne).
     */
    private function schedule(GameState $state, RenovationDefinition $work, string $financing): ScheduledWork
    {
        $delay = $work->delay();
        $funds = self::FINANCING_LOAN === $financing ? (int) $this->finance->ecoPtzFundsReleaseDays()->value : 0;
        $start = $state->currentDay + max(0, $funds) + $delay->leadDays;

        return new ScheduledWork($work->slug(), $start, $start + $delay->buildDays);
    }

    /**
     * The catalogue works currently under construction — resolved from the
     * scheduled slugs so the concurrency rule can weigh them. Unknown slugs
     * (never expected) are simply dropped.
     *
     * @return list<RenovationDefinition>
     */
    private function inProgressWorks(GameState $state): array
    {
        return array_values(array_filter(array_map(
            fn (ScheduledWork $pending): ?RenovationDefinition => $this->catalog->tryGet($pending->workSlug),
            $state->scheduledWorks,
        )));
    }
}
