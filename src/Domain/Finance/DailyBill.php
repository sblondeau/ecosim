<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * The day's energy money flows — electricity, the active heating fuel
 * (fuel oil OR wood pellets, mutually exclusive: one generator at a time),
 * and the surplus-resale credit.
 *
 * Lines are kept separate on purpose (multi-criteria principle, §1): the
 * player must see which carrier costs what — that is how electrifying the
 * heating (or switching fuel) becomes readable on the budget.
 */
final readonly class DailyBill
{
    public function __construct(
        /** Grid electricity bought today. */
        public Money $electricityCost,
        /** Fuel oil burnt today. */
        public Money $fuelOilCost,
        /** Surplus sold to the grid today (a credit). */
        public Money $surplusRevenue,
        /** Wood pellets burnt today (pellet boiler). */
        public Money $pelletCost = new Money(0),
    ) {
    }

    public static function zero(): self
    {
        return new self(Money::zero(), Money::zero(), Money::zero());
    }

    /**
     * Net cash out for the day (costs minus resale credit; can be negative on
     * a great summer day).
     */
    public function netCost(): Money
    {
        return $this->electricityCost->plus($this->fuelOilCost)->plus($this->pelletCost)->minus($this->surplusRevenue);
    }
}
