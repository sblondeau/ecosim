<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * The STATIC pedagogical content of a renovation work (§ panneau de décision,
 * étape 2): what it is and how it works, independent of the household.
 *
 * Deliberately NOT here:
 * - the **ROI** (payback) — computed from the household (net cost ÷ annual
 *   saving), so it lives in the view layer, not this fixed content;
 * - the **contextual caution** ("isolez d'abord") — dynamic, already carried by
 *   {@see RenovationDefinition::adviceFor()}.
 *
 * `details` is a plain string for now (the « Voir plus »). If a work ever needs
 * rich content (a diagram, images), it graduates to a per-work Twig partial
 * `game/work/_<slug>.html.twig` — the same escape hatch the scenario events use
 * ({@see \App\Domain\Scenario\ExplainedEvent}) — without touching this contract.
 */
final readonly class WorkPedagogy
{
    /**
     * @param list<string> $sources traceable references (ADEME…), shown in the
     *                              expanded view (§13)
     */
    public function __construct(
        /** 1-2 sentences: the mechanism (« ce que c'est »). Player-facing French. */
        public string $shortWhat,
        /** The « Voir plus » long form: detailed mechanism, when it pays off, pitfalls. */
        public string $details = '',
        public array $sources = [],
    ) {
    }
}
