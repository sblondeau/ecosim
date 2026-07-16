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
        /** Form value identifying the work ({@see \App\Domain\Finance\Renovation} backed value). */
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
    ) {
    }
}
