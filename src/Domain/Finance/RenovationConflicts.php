<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * The coherence rule for chantiers that replace the SAME exclusive equipment:
 * at most one of a group may be in flight at once (game-design §1). The group
 * membership itself is carried by each work ({@see
 * RenovationDefinition::exclusivityGroup()}) — this only compares them.
 *
 * Not an artificial gate: once a generator is installed you may still switch to
 * another (the coût d'accès stays the full price). It only forbids ordering a
 * second, conflicting chantier while the first is still being built.
 */
final readonly class RenovationConflicts
{
    /**
     * Whether ordering $ordered conflicts with a work already in progress —
     * both non-null and in the same {@see ExclusivityGroup}.
     *
     * @param list<RenovationDefinition> $inProgress
     */
    public function conflictsWithInProgress(RenovationDefinition $ordered, array $inProgress): bool
    {
        $group = $ordered->exclusivityGroup();
        if (null === $group) {
            return false;
        }

        foreach ($inProgress as $work) {
            if ($work->exclusivityGroup() === $group) {
                return true;
            }
        }

        return false;
    }
}
