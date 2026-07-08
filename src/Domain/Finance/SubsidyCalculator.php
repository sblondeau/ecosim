<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use function min;

/**
 * The generic income-based renovation prime (game-design §8, §15 — inspired by
 * MaPrimeRénov' without simulating the real scheme stacking, §18).
 *
 * Three mechanisms, all real: a rate that DECREASES as income grows (the
 * modest are helped most, up to 80 %), an absolute cap on the prime, and the
 * écrêtement — a minimum share of the cost always remains payable by the
 * household, whatever the stacking.
 */
final readonly class SubsidyCalculator
{
    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
    ) {
    }

    /**
     * The prime for a subsidised work costing $cost, given the household's
     * (fixed, scenario) income.
     */
    public function subsidyFor(Money $cost): Money
    {
        $rate = $this->rateForIncome($this->calibration->monthlyNetIncome()->value * 12.0);

        $prime = (int) round($cost->cents * $rate);
        $cap = Money::fromEuros($this->calibration->subsidyCap()->value)->cents;
        $maxShare = (int) round($cost->cents * $this->calibration->subsidyMaxShare()->value);

        return Money::fromCents(min($prime, $cap, $maxShare));
    }

    /**
     * Decreasing rate by annual income bracket (esprit MaPrimeRénov').
     */
    private function rateForIncome(float $annualIncome): float
    {
        if ($annualIncome <= $this->calibration->veryModestIncomeCeiling()->value) {
            return $this->calibration->veryModestSubsidyRate()->value;
        }

        if ($annualIncome <= $this->calibration->modestIncomeCeiling()->value) {
            return $this->calibration->modestSubsidyRate()->value;
        }

        if ($annualIncome <= $this->calibration->intermediateIncomeCeiling()->value) {
            return $this->calibration->intermediateSubsidyRate()->value;
        }

        return $this->calibration->upperSubsidyRate()->value;
    }
}
