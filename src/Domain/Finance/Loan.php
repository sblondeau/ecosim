<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use function intdiv;
use function min;

/**
 * The household's zero-interest loan account (éco-PTZ-like, game-design §8:
 * jusqu'à 50 000 €, 0 %, 20 ans).
 *
 * One account for the whole game (§15: « 1 type de prêt ») — each financed
 * renovation adds to it: the principal grows and the monthly payment grows by
 * the new amount spread over the full term. Zero interest means the total
 * repaid equals exactly the total borrowed. Immutable: borrowing or paying an
 * installment returns a new Loan.
 */
final readonly class Loan
{
    /** 20 years of monthly payments (éco-PTZ maximum duration). */
    private const int TERM_MONTHS = 240;

    public function __construct(
        /** Principal still to repay. */
        public Money $remaining,
        /** Installment due each month (1st of the month). */
        public Money $monthlyPayment,
        /** Total ever borrowed — checked against the regulatory cap. */
        public Money $borrowedTotal,
    ) {
    }

    public static function none(): self
    {
        return new self(Money::zero(), Money::zero(), Money::zero());
    }

    public function isActive(): bool
    {
        return $this->remaining->cents > 0;
    }

    /**
     * Add a financed amount: the monthly payment grows by amount/240 (rounded
     * up so the loan never outlives the term).
     */
    public function borrow(Money $amount): self
    {
        $installment = intdiv($amount->cents + self::TERM_MONTHS - 1, self::TERM_MONTHS);

        return new self(
            remaining: $this->remaining->plus($amount),
            monthlyPayment: $this->monthlyPayment->plus(Money::fromCents($installment)),
            borrowedTotal: $this->borrowedTotal->plus($amount),
        );
    }

    /**
     * What is actually due this month — the payment, capped by what remains
     * (the last installment is smaller).
     */
    public function installmentDue(): Money
    {
        return Money::fromCents(min($this->monthlyPayment->cents, $this->remaining->cents));
    }

    /**
     * The account after paying an installment. When fully repaid, the monthly
     * payment drops to zero.
     */
    public function afterPayment(Money $paid): self
    {
        $remaining = $this->remaining->minus($paid);

        if ($remaining->cents <= 0) {
            return new self(Money::zero(), Money::zero(), $this->borrowedTotal);
        }

        return new self($remaining, $this->monthlyPayment, $this->borrowedTotal);
    }
}
