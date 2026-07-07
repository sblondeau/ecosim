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
        // Today's energy balance
        public float $productionKwh,
        public float $demandKwh,
        public int $selfSufficiencyPct,
        public float $gridImportKwh,
        public float $gridExportKwh,
        // Heating & comfort
        public string $heatingLabel,
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
        // Cumulative period totals
        public float $totalProductionKwh,
        public float $totalImportKwh,
        public float $totalExportKwh,
        public int $totalSelfSufficiencyPct,
        public float $totalFuelOilLitres,
        public int $averageComfortPct,
    ) {
    }
}
