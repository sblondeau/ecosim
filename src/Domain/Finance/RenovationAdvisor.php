<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\Household;
use LogicException;

use function sprintf;

/**
 * Non-prescriptive advice about renovations (game-design: pédagogie par les
 * systèmes, pas de dirigisme). For an available work and the current house,
 * returns an informative repère (Info) or a caution against a genuine
 * sequencing mistake (Caution) — never a "do this next". A caution is reserved
 * for the few real ordering mistakes: a heat pump in a poorly-insulated house,
 * glazing prioritised before the envelope is treated, and double-flow
 * ventilation installed before the envelope is insulated. Pure and deterministic.
 */
final readonly class RenovationAdvisor
{
    public function __construct(
        private RenovationCatalog $catalog = new RenovationCatalog(),
    ) {
    }

    /**
     * Every work carries a word of advice: the catalogue answers migrated
     * works, the legacy match answers the rest (exhaustive together).
     */
    public function adviceFor(Renovation $work, Household $household): RenovationAdvice
    {
        $definition = $this->catalog->tryGet($work->value);
        if (null !== $definition) {
            return $definition->adviceFor($household);
        }

        return match ($work) {
            // Migrated to the catalogue (tasks 3-5): a definition always
            // answers these before the match is reached. Reaching here would
            // mean defaultWorks() lost an entry — a real bug, not a legal state.
            default => throw new LogicException(sprintf('"%s" is migrated to the renovation catalogue — the bridge above should have answered it.', $work->value)),
        };
    }
}
