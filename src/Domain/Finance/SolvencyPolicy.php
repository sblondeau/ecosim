<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * The bank-solvency rule (§ contrainte, levier ③): a household's debt-to-income
 * ratio must stay under the HCSF 35 % wall, so a lender refuses a loan that
 * would push it past. Not an artificial gate — the real rule (a bank does not
 * lend to an over-indebted household), which caps how much éco-PTZ can be piled
 * up regardless of the 50 000 € plafond.
 *
 * The ratio counts the explicit home loan plus the éco-PTZ monthly payment
 * (§ contrainte ③): the PTZ's 0 % does not exempt it — it is the mensualité
 * that weighs. Pure and deterministic (only the {@see Loan} and calibration).
 */
final readonly class SolvencyPolicy
{
    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
    ) {
    }

    /** Debt-to-income ratio with the current loan: (mortgage + PTZ monthly) / net income. */
    public function debtRatio(Loan $loan): float
    {
        $charges = $this->calibration->mortgageMonthlyPayment()->value + $loan->monthlyPayment->euros();

        return $charges / $this->calibration->monthlyNetIncome()->value;
    }

    /** The ratio the household would carry after borrowing $amount more on the éco-PTZ. */
    public function debtRatioAfterBorrowing(Loan $loan, Money $amount): float
    {
        return $this->debtRatio($loan->borrow($amount));
    }

    /** Whether borrowing $amount more keeps the ratio at or under the ceiling. */
    public function allowsBorrowing(Loan $loan, Money $amount): bool
    {
        return $this->debtRatioAfterBorrowing($loan, $amount) <= $this->ceiling();
    }

    public function ceiling(): float
    {
        return $this->calibration->debtRatioCeiling()->value;
    }
}
