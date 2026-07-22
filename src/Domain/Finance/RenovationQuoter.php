<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\Household;

/**
 * Turns a work's own offer into a signable quote by applying the FINANCING
 * POLICY: the prime perimeter and its income-based rate.
 *
 * That policy is identical for every work, which is exactly why definitions
 * declare offers ({@see RenovationOffer}) and not finished quotes — the next
 * reform of the real scheme is a change here, not across fifteen classes.
 *
 * Works are instantaneous in Phase 0-1 (no permitting/construction delays yet
 * — the real « délai » access-cost lever arrives with later phases).
 */
final readonly class RenovationQuoter
{
    public function __construct(
        private SubsidyCalculator $subsidy = new SubsidyCalculator(),
    ) {
    }

    /** Null when the work does not apply to this house — the UI hides it. */
    public function quote(RenovationDefinition $work, Household $household): ?RenovationQuote
    {
        $offer = $work->offerFor($household);
        if (null === $offer) {
            return null;
        }

        return new RenovationQuote(
            workSlug: $work->slug(),
            title: $offer->title,
            cost: $offer->cost,
            subsidy: $work->qualifiesForEnergyAid()
                ? $this->subsidy->subsidyFor($offer->cost)
                : Money::zero(),
            resultingHousehold: $offer->resultingHousehold,
        );
    }
}
