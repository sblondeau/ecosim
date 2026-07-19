<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\Household;

/**
 * A priced, ready-to-sign renovation: what it costs, what the prime covers,
 * and what the household becomes once the work is done.
 */
final readonly class RenovationQuote
{
    public function __construct(
        /** Slug of the work this quote prices ({@see RenovationDefinition::slug()}). */
        public string $workSlug,
        /** Player-facing description of the work (French). */
        public string $title,
        public Money $cost,
        /** Income-based prime already deducted from what the player pays. */
        public Money $subsidy,
        /** The household configuration after the work. */
        public Household $resultingHousehold,
    ) {
    }

    /**
     * What the player actually pays (reste à charge).
     */
    public function netCost(): Money
    {
        return $this->cost->minus($this->subsidy);
    }
}
