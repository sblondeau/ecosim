<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Finance\Loan;
use App\Domain\Finance\Money;
use App\Domain\Finance\SolvencyPolicy;
use PHPUnit\Framework\TestCase;

/**
 * The bank-solvency rule (§ contrainte ③): a loan is refused if it would push
 * the household's debt-to-income ratio past the HCSF 35 % wall. The éco-PTZ
 * counts (its 0 % does not exempt — the monthly payment weighs).
 */
final class SolvencyPolicyTest extends TestCase
{
    public function testTheStartingDebtRatioIsTheMortgageAlone(): void
    {
        // 840 mortgage / 2800 income = 30 % — the ACPR average, no loan yet.
        self::assertEqualsWithDelta(0.30, new SolvencyPolicy()->debtRatio(Loan::none()), 0.0001);
    }

    public function testTheCeilingIsThirtyFivePercent(): void
    {
        self::assertEqualsWithDelta(0.35, new SolvencyPolicy()->ceiling(), 0.0001);
    }

    public function testBorrowingUpToTheWallIsAllowed(): void
    {
        // 33 600 € over 240 months = 140 €/mo → (840 + 140) / 2800 = 35 % exactly.
        self::assertTrue(new SolvencyPolicy()->allowsBorrowing(Loan::none(), Money::fromEuros(33600.0)));
    }

    public function testBorrowingBeyondTheWallIsRefused(): void
    {
        // 34 000 € → 142 €/mo → (840 + 142) / 2800 = 35,07 % > 35 %.
        self::assertFalse(new SolvencyPolicy()->allowsBorrowing(Loan::none(), Money::fromEuros(34000.0)));
    }

    public function testAnExistingLoanCountsTowardTheRatio(): void
    {
        $policy = new SolvencyPolicy();
        $existing = Loan::none()->borrow(Money::fromEuros(20000.0)); // ~84 €/mo

        self::assertGreaterThan($policy->debtRatio(Loan::none()), $policy->debtRatio($existing), 'An active éco-PTZ raises the ratio.');
        self::assertFalse($policy->allowsBorrowing($existing, Money::fromEuros(20000.0)), 'A second 20k would push past 35 %.');
    }
}
