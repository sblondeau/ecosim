<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Finance\Money;

/**
 * What one year in a given house configuration adds up to, over the reference
 * weather year (see {@see AnnualOutcomeEstimator}).
 */
final readonly class AnnualOutcome
{
    public function __construct(
        public Money $netEnergyCost,
        public int $averageComfortScore,
        public float $productionKwh,
        public float $selfSufficiencyRatio,
        /** Year's electricity use (final energy), for the DPE energy/climate labels. */
        public float $electricityKwh = 0.0,
        /** Year's fuel-oil use, in litres, for the DPE labels. */
        public float $fuelOilLitres = 0.0,
    ) {
    }
}
