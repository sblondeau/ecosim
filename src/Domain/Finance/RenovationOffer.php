<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\Household;

/**
 * What a work declares of ITSELF: its price tag and what the house becomes.
 *
 * Deliberately NOT a {@see RenovationQuote}: the prime and the éco-PTZ
 * perimeter are POLICY, identical for every work, and they stay in
 * {@see RenovationQuoter}. Letting each definition build its own quote would
 * copy the subsidy call into fifteen classes, and the next reform of the real
 * scheme would become a fifteen-file chore.
 */
final readonly class RenovationOffer
{
    public function __construct(
        /** Player-facing description (French), possibly dynamic ("Menuiseries — Triple vitrage"). */
        public string $title,
        /** Sticker price, before any prime. */
        public Money $cost,
        /** The household configuration once the work is done. */
        public Household $resultingHousehold,
    ) {
    }
}
