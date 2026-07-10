<?php

declare(strict_types=1);

namespace App\Application;

/**
 * Flat read model handed to the presentation layer (game-design §3).
 *
 * The template sees ONLY this object — never the domain. Everything is a scalar
 * ready to display, so the whole UI can be swapped (Twig today, canvas/three.js
 * tomorrow) without touching the simulation. Built by {@see GameViewFactory}.
 */
final readonly class GameView
{
    public function __construct(
        public int $dayNumber,
        public string $dateLabel,
        public string $seasonLabel,
        public int $horizonDays,
        public bool $finished,
        public int $progressPct,
        // Weather
        public int $cloudPct,
        public float $temperatureC,
        /** The last ≤30 days of weather, recomputed from the seed (no storage). */
        public SparklineView $weatherSparkline,
        /** The semantic scene model driving the SVG house (game-design §17). */
        public HouseSceneView $scene,
        // Today's energy balance
        public float $productionKwh,
        public float $demandKwh,
        public int $selfSufficiencyPct,
        public float $gridImportKwh,
        public float $gridExportKwh,
        // Finances (today's ledger + savings)
        public string $savingsLabel,
        public bool $savingsNegative,
        public string $electricityCostLabel,
        public string $fuelOilCostLabel,
        public string $surplusRevenueLabel,
        public bool $incomeCreditedToday,
        public string $monthlyIncomeLabel,
        public string $monthlyExpensesLabel,
        public string $monthlyNetIncomeLabel,
        // Patrimoine (non-liquid, realisable on resale only — §8)
        public string $propertyValueLabel,
        // Loan (éco-PTZ account)
        public bool $loanActive,
        public string $loanMonthlyPaymentLabel,
        public string $loanRemainingLabel,
        // Heating & comfort
        public string $heatingLabel,
        /** The scripted breakdown happened and the boiler is still dead (no heating). */
        public bool $boilerBroken,
        public string $insulationLabel,
        public string $dpeLetter,
        public float $heatingElectricityKwh,
        public float $fuelOilLitres,
        public int $comfortScorePct,
        public float $indoorTemperatureC,
        public float $feltTemperatureC,
        // Equipment
        public float $solarKwc,
        public float $batteryLevelKwh,
        public float $batteryCapacityKwh,
        public int $batteryPct,
        /** Energy the battery delivered to the home today (its visible usefulness). */
        public float $batteryDischargedKwh,
        // Cumulative period totals
        public float $totalProductionKwh,
        public float $totalImportKwh,
        public float $totalExportKwh,
        public int $totalSelfSufficiencyPct,
        public float $totalFuelOilLitres,
        public int $averageComfortPct,
        public string $totalElectricityCostLabel,
        public string $totalFuelOilCostLabel,
        public string $totalSurplusRevenueLabel,
        public string $totalNetEnergyCostLabel,
        /**
         * Player-facing explanations of the metrics, keyed by topic. Built
         * from the calibration registry so every number quoted in a tooltip
         * is the one actually simulated (§13 traceability as a feature).
         *
         * @var array<string, string>
         */
        public array $help = [],
        /**
         * Renovations available right now, keyed by work slug.
         *
         * @var array<string, ActionView>
         */
        public array $actions = [],
        /** The factual per-axis report — only once the horizon is reached. */
        public ?EndReportView $endReport = null,
    ) {
    }
}
