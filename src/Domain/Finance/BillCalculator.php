<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\HeatingConsumption;
use App\Domain\Energy\EnergyBalance;

/**
 * Prices one settled day: energy flows (kWh, litres) × fixed tariffs → the
 * day's {@see DailyBill}. Pure pricing — no evolution over time in this phase
 * (game-design §15: tarifs fixes).
 */
final readonly class BillCalculator
{
    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
    ) {
    }

    public function billFor(EnergyBalance $balance, HeatingConsumption $heating): DailyBill
    {
        return new DailyBill(
            electricityCost: Money::fromEuros(
                $balance->gridImportKwh * $this->calibration->electricityPricePerKwh()->value,
            ),
            fuelOilCost: Money::fromEuros(
                $heating->fuelOilLitres * $this->calibration->fuelOilPricePerLitre()->value,
            ),
            surplusRevenue: Money::fromEuros(
                $balance->gridExportKwh * $this->calibration->surplusSellPricePerKwh()->value,
            ),
        );
    }
}
