<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Finance\Loan;
use App\Domain\Finance\Money;
use PHPUnit\Framework\TestCase;

final class LoanTest extends TestCase
{
    public function testNoneIsInactiveAndOwesNothing(): void
    {
        $loan = Loan::none();

        self::assertFalse($loan->isActive());
        self::assertSame(0, $loan->installmentDue()->cents);
    }

    public function testBorrowSpreadsTheAmountOverTwentyYears(): void
    {
        $loan = Loan::none()->borrow(Money::fromEuros(24000.0));

        self::assertTrue($loan->isActive());
        // 24 000 € / 240 mois = 100 €/mois, taux zéro.
        self::assertSame(100_00, $loan->monthlyPayment->cents);
        self::assertSame(24000_00, $loan->remaining->cents);
        self::assertSame(24000_00, $loan->borrowedTotal->cents);
    }

    public function testSuccessiveBorrowingsStackOnTheSameAccount(): void
    {
        $loan = Loan::none()->borrow(Money::fromEuros(12000.0))->borrow(Money::fromEuros(7800.0));

        self::assertSame(19800_00, $loan->remaining->cents);
        // 50 €/mois + 32,50 €/mois.
        self::assertSame(50_00 + 32_50, $loan->monthlyPayment->cents);
    }

    public function testRemainingMonthsCountsThePaymentsAhead(): void
    {
        self::assertSame(0, Loan::none()->remainingMonths());

        $loan = Loan::none()->borrow(Money::fromEuros(7800.0)); // 32,50 €/mois
        self::assertSame(240, $loan->remainingMonths(), 'A fresh loan runs the full 20 years.');

        // After a few payments, fewer months remain.
        $due = $loan->installmentDue();
        $loan = $loan->afterPayment($due)->afterPayment($due);
        self::assertSame(238, $loan->remainingMonths());
    }

    public function testZeroInterestTotalRepaidEqualsTotalBorrowed(): void
    {
        $loan = Loan::none()->borrow(Money::fromEuros(10000.0));

        $paid = Money::zero();
        $months = 0;
        while ($loan->isActive() && $months < 300) {
            $due = $loan->installmentDue();
            $paid = $paid->plus($due);
            $loan = $loan->afterPayment($due);
            ++$months;
        }

        self::assertSame(10000_00, $paid->cents, '0% interest: not one cent more than borrowed.');
        self::assertLessThanOrEqual(240, $months, 'Repaid within the 20-year term.');
        self::assertSame(0, $loan->monthlyPayment->cents, 'A repaid loan stops charging.');
    }
}
