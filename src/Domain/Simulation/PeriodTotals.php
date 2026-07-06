<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Energy\EnergyBalance;

/**
 * Running cumulative energy totals over a game (game-design §8, §15).
 *
 * Immutable: {@see self::add()} folds one more day's {@see EnergyBalance} into
 * fresh totals. Used for the period summary shown on the dashboard and, later,
 * for the end-of-game report.
 */
final readonly class PeriodTotals
{
    public function __construct(
        public float $productionKwh = 0.0,
        public float $demandKwh = 0.0,
        public float $importKwh = 0.0,
        public float $exportKwh = 0.0,
    ) {
    }

    public function add(EnergyBalance $balance): self
    {
        return new self(
            productionKwh: $this->productionKwh + $balance->productionKwh,
            demandKwh: $this->demandKwh + $balance->demandKwh,
            importKwh: $this->importKwh + $balance->gridImportKwh,
            exportKwh: $this->exportKwh + $balance->gridExportKwh,
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
}
