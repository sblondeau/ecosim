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
    ) {
    }
}
