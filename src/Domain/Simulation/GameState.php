<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Building\Household;
use App\Domain\Finance\Loan;
use App\Domain\Finance\Money;

/**
 * The mutable-over-time state of a game, as an immutable snapshot.
 *
 * Everything that changes as the game is played lives here: the current day
 * (tick counter), the household configuration (equipment, insulation, heating
 * — the player's decisions, game-design §8/§18), the battery charge carried
 * from day to day, the savings account, and the running totals. Advancing a
 * day is a pure transition {@see self::advanced()} returning a new state —
 * the simulation core never mutates in place (game-design §3).
 */
final readonly class GameState
{
    /**
     * @param int<0, max> $currentDay
     */
    public function __construct(
        public int $currentDay,
        public Household $household,
        public float $batteryLevelKwh,
        public Money $savings,
        public Loan $loan,
        public PeriodTotals $totals,
    ) {
    }

    public static function start(Household $household, Money $savings): self
    {
        return new self(0, $household, 0.0, $savings, Loan::none(), new PeriodTotals());
    }

    /**
     * The state after living through one settled day: the day's income lands,
     * the day's net energy bill is paid. The household carries over unchanged
     * — renovations/installations become explicit player actions in a later
     * step. Savings may go into overdraft (no arbitrary game over, §1).
     */
    public function advanced(DailySnapshot $day): self
    {
        return new self(
            $this->currentDay + 1,
            $this->household,
            $day->balance->batteryLevelKwh,
            $this->savings
                ->plus($day->incomeCredited)
                ->minus($day->bill->netCost())
                ->minus($day->loanPayment),
            $this->loan->afterPayment($day->loanPayment),
            $this->totals->add($day),
        );
    }

    /**
     * The state right after signing a renovation: new household configuration,
     * savings after the cash part, loan after the financed part. The day does
     * not advance — deciding is not living.
     */
    public function renovated(Household $household, Money $savings, Loan $loan): self
    {
        return new self(
            $this->currentDay,
            $household,
            $this->batteryLevelKwh,
            $savings,
            $loan,
            $this->totals,
        );
    }
}
