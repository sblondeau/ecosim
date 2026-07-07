<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Building\HeatingConsumption;
use App\Domain\Building\ThermalComfort;
use App\Domain\Energy\EnergyBalance;
use App\Domain\Finance\DailyBill;
use App\Domain\Finance\Money;
use App\Domain\Time\GameDate;
use App\Domain\Weather\Weather;

/**
 * A read-only view of one simulated day: its date, weather, energy balance,
 * heating consumption, thermal comfort and the day's ledger (bill + income).
 *
 * Produced by {@see SimulationEngine::snapshot()} without mutating the game —
 * it is what the dashboard shows for the current day before the player chooses
 * to live through it.
 */
final readonly class DailySnapshot
{
    public function __construct(
        public GameDate $date,
        public Weather $weather,
        public EnergyBalance $balance,
        public HeatingConsumption $heating,
        public ThermalComfort $comfort,
        public DailyBill $bill,
        /** Net income credited this day (non-zero on the 1st of each month). */
        public Money $incomeCredited,
    ) {
    }
}
