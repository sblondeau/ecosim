<?php

declare(strict_types=1);

namespace App\Domain\Energy;

/**
 * The outcome of settling one day of energy flows (game-design §8).
 *
 * All figures are in kWh for a single day. Invariants held by
 * {@see EnergyBalanceCalculator}:
 *   selfConsumed + gridImport      = demand
 *   production                     = selfConsumed(direct part) + charged + gridExport   (up to battery losses)
 *   batteryLevelKwh ∈ [0, capacity]
 */
final readonly class EnergyBalance
{
    public function __construct(
        public float $productionKwh,
        public float $demandKwh,
        /** Demand met from own production, directly or via the battery. */
        public float $selfConsumedKwh,
        public float $gridImportKwh,
        public float $gridExportKwh,
        /** Surplus stored into the battery over the day (input side). */
        public float $batteryChargedKwh,
        /** Energy delivered by the battery to the load over the day (output side). */
        public float $batteryDischargedKwh,
        /** Battery charge level at the end of the day. */
        public float $batteryLevelKwh,
    ) {
    }

    /**
     * Share of the demand covered by own production (0 to 1).
     */
    public function selfSufficiencyRatio(): float
    {
        if ($this->demandKwh <= 0.0) {
            return 1.0;
        }

        return $this->selfConsumedKwh / $this->demandKwh;
    }
}
