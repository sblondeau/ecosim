<?php

declare(strict_types=1);

namespace App\Domain\Building;

/**
 * A dwelling's DPE, both official labels at once: energy (primary energy per m²)
 * and climate (CO₂ per m²), each with its class and where the value sits inside
 * that class's band, plus the final class (the worse of the two, 2021 rule).
 *
 * Read-only view produced by {@see DpeCertifier} from the year's real energy —
 * so the class and the cursor position are earned, not looked up.
 */
final readonly class DpeAssessment
{
    public function __construct(
        /** Primary-energy use, kWhEP/m²/an. */
        public float $energyIntensity,
        public DpeClass $energyClass,
        /** Position inside the energy class band, 0 (best edge) to 100 (about to drop a letter). */
        public float $energyBandFillPct,
        /** Emissions, kgCO₂/m²/an. */
        public float $climateIntensity,
        public DpeClass $climateClass,
        public float $climateBandFillPct,
        /** The letter shown as THE DPE: the worse of energy and climate. */
        public DpeClass $finalClass,
    ) {
    }
}
