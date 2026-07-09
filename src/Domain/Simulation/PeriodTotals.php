<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Finance\Money;

use function round;

/**
 * Running cumulative totals over a game (game-design §8, §15).
 *
 * Immutable: {@see self::add()} folds one more settled day into fresh totals.
 * Used for the period summary shown on the dashboard and, later, for the
 * end-of-game report.
 */
final readonly class PeriodTotals
{
    /**
     * @param float       $fuelOilLitres total fuel oil burnt by the boiler since day 1 —
     *                                   feeds the fioul line of the bill and the
     *                                   end-of-game report; stops growing once the
     *                                   player switches to the heat pump
     * @param int<0, max> $days
     */
    public function __construct(
        public float $productionKwh = 0.0,
        public float $demandKwh = 0.0,
        public float $importKwh = 0.0,
        public float $exportKwh = 0.0,
        public float $fuelOilLitres = 0.0,
        public float $comfortScoreSum = 0.0,
        public Money $electricityCost = new Money(0),
        public Money $fuelOilCost = new Money(0),
        public Money $surplusRevenue = new Money(0),
        public int $days = 0,
    ) {
    }

    public function add(DailySnapshot $day): self
    {
        return new self(
            productionKwh: $this->productionKwh + $day->balance->productionKwh,
            demandKwh: $this->demandKwh + $day->balance->demandKwh,
            importKwh: $this->importKwh + $day->balance->gridImportKwh,
            exportKwh: $this->exportKwh + $day->balance->gridExportKwh,
            fuelOilLitres: $this->fuelOilLitres + $day->heating->fuelOilLitres,
            comfortScoreSum: $this->comfortScoreSum + $day->comfort->score,
            electricityCost: $this->electricityCost->plus($day->bill->electricityCost),
            fuelOilCost: $this->fuelOilCost->plus($day->bill->fuelOilCost),
            surplusRevenue: $this->surplusRevenue->plus($day->bill->surplusRevenue),
            days: $this->days + 1,
        );
    }

    /**
     * Share of the total demand covered by own production over the period (0 to 1).
     */
    public function selfSufficiencyRatio(): float
    {
        if ($this->demandKwh <= 0.0) {
            return 1.0;
        }

        return ($this->demandKwh - $this->importKwh) / $this->demandKwh;
    }

    /**
     * Average comfort score over the lived days (100 before any day is lived).
     */
    public function averageComfortScore(): int
    {
        if (0 === $this->days) {
            return 100;
        }

        return (int) round($this->comfortScoreSum / $this->days);
    }

    /**
     * Total energy money out (both bill lines, resale credit deducted).
     */
    public function netEnergyCost(): Money
    {
        return $this->electricityCost->plus($this->fuelOilCost)->minus($this->surplusRevenue);
    }
}
