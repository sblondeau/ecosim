<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use function in_array;

/**
 * Which works cannot have two chantiers in flight at once because they replace
 * the SAME exclusive equipment — you don't queue a heat pump AND a pellet
 * boiler, you pick one generator.
 *
 * This is a coherence rule, not an artificial gate (§1): once a generator is
 * installed you may still switch to another (the coût d'accès stays the full
 * price). It only forbids ordering a second, conflicting chantier while the
 * first is still being built.
 *
 * The emergency boiler_repair is deliberately NOT in the group: you must always
 * be able to survive the panne, even with a heat pump already on order.
 */
final readonly class RenovationConflicts
{
    private const string HEATING_GENERATOR = 'heating-generator';

    public function groupFor(string $workSlug): ?string
    {
        return in_array($workSlug, ['heat_pump', 'pellet_boiler'], true)
            ? self::HEATING_GENERATOR
            : null;
    }

    /**
     * Whether ordering $workSlug conflicts with a chantier already in progress.
     *
     * @param list<string> $inProgressSlugs
     */
    public function conflictsWithInProgress(string $workSlug, array $inProgressSlugs): bool
    {
        $group = $this->groupFor($workSlug);
        if (null === $group) {
            return false;
        }

        foreach ($inProgressSlugs as $inProgress) {
            if ($this->groupFor($inProgress) === $group) {
                return true;
            }
        }

        return false;
    }
}
