<?php

declare(strict_types=1);

namespace App\Application;

/**
 * The factual end-of-game report, one block per axis (game-design §1, §8).
 *
 * Deliberately NO aggregate: savings (liquid) and property value (realisable
 * on resale only) are never summed — the axes stay separate, the player reads
 * the facts and draws the conclusions. Tone: an energy balance sheet, not a
 * score screen.
 */
final readonly class EndReportView
{
    public function __construct(
        public int $daysLived,
        // Finances (liquid)
        public string $savingsStartLabel,
        public string $savingsEndLabel,
        public string $savingsDeltaLabel,
        public bool $savingsDeltaNegative,
        // Patrimoine (non-liquid) — the remaining debt is shown next to the
        // property value but never netted against it in an aggregate.
        public string $dpeStartLetter,
        public string $dpeEndLetter,
        public string $propertyStartLabel,
        public string $propertyEndLabel,
        public string $propertyDeltaLabel,
        public bool $loanActive,
        public string $loanRemainingLabel,
        // Confort
        public int $averageComfortPct,
        // Énergie
        public float $totalFuelOilLitres,
        public int $totalSelfSufficiencyPct,
        public string $totalNetEnergyCostLabel,
    ) {
    }
}
