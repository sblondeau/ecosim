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
 * from day to day, the savings account, the running totals, and the renovation
 * chantiers ordered but not yet applied ({@see ScheduledWork} — the « délai »
 * access-cost lever, §1). Advancing a day is a pure transition
 * {@see self::advanced()} returning a new state — the simulation core never
 * mutates in place (game-design §3).
 */
final readonly class GameState
{
    /**
     * @param int<0, max>         $currentDay
     * @param list<ScheduledWork> $scheduledWorks chantiers ordered but not yet posed
     */
    public function __construct(
        public int $currentDay,
        public Household $household,
        public float $batteryLevelKwh,
        public Money $savings,
        public Loan $loan,
        public PeriodTotals $totals,
        public array $scheduledWorks = [],
    ) {
    }

    public static function start(Household $household, Money $savings): self
    {
        return new self(0, $household, 0.0, $savings, Loan::none(), new PeriodTotals());
    }

    /**
     * The state after living through one settled day: the day's income lands,
     * the day's net energy bill is paid. The household and the pending
     * chantiers carry over unchanged — completing a chantier is the engine's
     * job, on its completion day. Savings may go into overdraft (no arbitrary
     * game over, §1).
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
            $this->scheduledWorks,
        );
    }

    /**
     * The same day with a different household — for scripted events that hit
     * the equipment (boiler breakdown) or a chantier landing on the household,
     * without touching the money.
     */
    public function withHousehold(Household $household): self
    {
        return new self(
            $this->currentDay,
            $household,
            $this->batteryLevelKwh,
            $this->savings,
            $this->loan,
            $this->totals,
            $this->scheduledWorks,
        );
    }

    /**
     * The state right after signing a renovation applied INSTANTLY: new
     * household, savings after the cash part, loan after the financed part. The
     * day does not advance. Kept for state setups and hypotheticals; the live
     * game defers the household effect via {@see self::scheduling()}.
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
            $this->scheduledWorks,
        );
    }

    /**
     * The state right after ORDERING a renovation: the money is committed now
     * (savings for the cash part, loan for the financed part), but the
     * household is untouched — the chantier is added to the schedule and its
     * effect lands later, on {@see ScheduledWork::$completionDay}. The day does
     * not advance.
     */
    public function scheduling(Money $savings, Loan $loan, ScheduledWork $work): self
    {
        return new self(
            $this->currentDay,
            $this->household,
            $this->batteryLevelKwh,
            $savings,
            $loan,
            $this->totals,
            [...$this->scheduledWorks, $work],
        );
    }

    /**
     * The same state with a rewritten schedule — the engine uses it to drop
     * chantiers it has just applied.
     *
     * @param list<ScheduledWork> $scheduledWorks
     */
    public function withScheduledWorks(array $scheduledWorks): self
    {
        return new self(
            $this->currentDay,
            $this->household,
            $this->batteryLevelKwh,
            $this->savings,
            $this->loan,
            $this->totals,
            $scheduledWorks,
        );
    }
}
