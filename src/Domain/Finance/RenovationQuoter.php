<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\Household;
use LogicException;

use function sprintf;

/**
 * Prices a renovation for a given household: cost (sourced), prime, and the
 * resulting household. Returns null when the work does not apply (already
 * done, nothing left to upgrade) — the UI simply hides the action.
 *
 * Works are instantaneous in Phase 0-1 (no permitting/construction delays yet
 * — the real « délai » access-cost lever arrives with later phases).
 */
final readonly class RenovationQuoter
{
    public function __construct(
        private SubsidyCalculator $subsidy = new SubsidyCalculator(),
        private RenovationCatalog $catalog = new RenovationCatalog(),
    ) {
    }

    public function quote(Renovation $work, Household $household): ?RenovationQuote
    {
        // Bridge, while works migrate one by one: a definition wins over the
        // legacy match. The match shrinks at each batch and dies in task 6.
        $definition = $this->catalog->tryGet($work->value);
        if (null !== $definition) {
            return $this->fromDefinition($work, $definition, $household);
        }

        return match ($work) {
            // Migrated to the catalogue (tasks 3-5): a definition always
            // answers these before the match is reached. Reaching here would
            // mean defaultWorks() lost an entry — a real bug, not a legal state.
            default => throw new LogicException(sprintf('"%s" is migrated to the renovation catalogue — the bridge above should have answered it.', $work->value)),
        };
    }

    /**
     * Turns a work's own offer into a signable quote by applying the FINANCING
     * POLICY — the prime perimeter and rate. That policy is identical for every
     * work, which is exactly why definitions declare offers and not quotes.
     */
    private function fromDefinition(Renovation $work, RenovationDefinition $definition, Household $household): ?RenovationQuote
    {
        $offer = $definition->offerFor($household);
        if (null === $offer) {
            return null;
        }

        return new RenovationQuote(
            work: $work,
            title: $offer->title,
            cost: $offer->cost,
            subsidy: $definition->isEnergyPerformanceWork()
                ? $this->subsidy->subsidyFor($offer->cost)
                : Money::zero(),
            resultingHousehold: $offer->resultingHousehold,
        );
    }
}
