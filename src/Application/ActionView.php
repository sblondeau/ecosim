<?php

declare(strict_types=1);

namespace App\Application;

/**
 * One renovation the player can order right now, ready for display: the quote
 * (cost, prime, reste à charge) and which financing routes are open.
 */
final readonly class ActionView
{
    public function __construct(
        /** Form value identifying the work ({@see \App\Domain\Finance\RenovationDefinition::slug()}). */
        public string $work,
        public string $title,
        public string $costLabel,
        /** Empty string when the work has no prime (solar, battery). */
        public string $subsidyLabel,
        public string $netCostLabel,
        /** Can the player pay cash (savings sufficient)? */
        public bool $cashAllowed,
        /** Can the player finance it with the zero-interest loan? */
        public bool $loanAllowed,
        /** Monthly éco-PTZ installment this work would add (empty if not loan-eligible). */
        public string $loanMonthlyLabel = '',
        /** Debt ratio the household would carry if this work is loan-financed (« 34 % »); '' if not loan-eligible. */
        public string $loanDebtRatioAfterLabel = '',
        /**
         * What the work would change over a reference weather year (bill,
         * comfort, production…) — honest estimates, never exact promises.
         *
         * @var list<string>
         */
        public array $effectLabels = [],
        /** Advice level for this work given the current house: 'info' | 'caution' | '' (none). */
        public string $adviceLevel = '',
        /** Player-facing advice message (French), empty when no advice applies. */
        public string $adviceMessage = '',
        /** Template path of the drawer icon (the scene asset). @see RenovationDefinition::iconAsset() */
        public string $iconAsset = '',
        /** How long the chantier takes once ordered (« Posé ~4 j après commande ») — shown before ordering. */
        public string $delayLabel = '',
        /** Whether this work's chantier is already ordered and pending (§ délais). */
        public bool $inProgress = false,
        /** When in progress: the pose countdown (« Chantier en cours · posé dans 3 j »). */
        public string $progressLabel = '',
        /**
         * Whether this (professional) work is orderable but blocked right now
         * because another pro chantier occupies the single crew (§ contrainte ①).
         * Shown disabled with a reason rather than hidden. Gestes are never busy.
         */
        public bool $crewBusy = false,
    ) {
    }
}
