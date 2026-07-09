<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use function intdiv;
use function number_format;

use const PHP_ROUND_HALF_UP;

use function round;

/**
 * An amount of money, stored as integer cents.
 *
 * Integer arithmetic keeps the ledger exact — energy quantities may be floats,
 * but every euro amount is rounded once (when a price is applied) and then
 * only added/subtracted. Negative amounts are allowed: the savings account can
 * run into overdraft (no arbitrary game over, game-design §1 — the end report
 * states the facts).
 */
final readonly class Money
{
    public function __construct(
        public int $cents,
    ) {
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function fromEuros(float $euros): self
    {
        return new self((int) round($euros * 100.0, 0, PHP_ROUND_HALF_UP));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function minus(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function euros(): float
    {
        return $this->cents / 100.0;
    }

    /**
     * French display format: "1 234,56 €" (minus sign leading when negative).
     */
    public function format(): string
    {
        $sign = $this->cents < 0 ? '−' : '';
        $absolute = $this->cents < 0 ? -$this->cents : $this->cents;

        return $sign.number_format(intdiv($absolute, 100) + ($absolute % 100) / 100, 2, ',', ' ').' €';
    }
}
