<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Finance\Work\BoilerRepairWork;

/**
 * The renovation-bandwidth rule (§ contrainte, levier ①): a household runs
 * **one professional chantier at a time**. Ordering a work that needs an RGE
 * pro is refused while another pro chantier is still in flight — you sequence
 * the big works across the year, which is what makes the délais bite.
 *
 * Self-doable gestes ({@see RenovationDefinition::requiresProfessional()} ==
 * false: draught-proofing, thermal curtains, the plug-and-play solar kit) run
 * in parallel without limit. The emergency boiler repair, though it needs a
 * professional, is exempt — you must be able to survive the panne even with a
 * chantier already under way.
 *
 * This subsumes the former heating-generator exclusivity: two generators both
 * need a pro, so "one pro at a time" already forbids PAC + granulés at once.
 */
final readonly class RenovationConcurrency
{
    /**
     * Whether $ordered may be ordered given the works already in flight.
     *
     * @param list<RenovationDefinition> $inProgress
     */
    public function allowsOrdering(RenovationDefinition $ordered, array $inProgress): bool
    {
        if (!$this->occupiesTheCrew($ordered)) {
            return true;
        }

        foreach ($inProgress as $work) {
            if ($this->occupiesTheCrew($work)) {
                return false;
            }
        }

        return true;
    }

    /** A pro chantier that takes the single crew slot — everything but a geste or the emergency repair. */
    private function occupiesTheCrew(RenovationDefinition $work): bool
    {
        return $work->requiresProfessional() && BoilerRepairWork::SLUG !== $work->slug();
    }
}
