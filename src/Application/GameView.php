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
        /** Net change to savings today (income − bill − loan payment), signed. */
        public string $dayNetLabel,
        public bool $dayNetNegative,
        public string $monthlyIncomeLabel,
        /** Living-cost forfait (INSEE) — the "vie courante" monthly line. */
        public string $monthlyExpensesLabel,
        /** Estimated monthly energy cost (reference year ÷ 12, net of resale). */
        public string $monthlyEnergyCostLabel,
        /** True disposable income: income − living − energy − loan. */
        public string $monthlyLeftoverLabel,
        public bool $monthlyLeftoverNegative,
        // Fuel poverty (ONPE taux d'effort énergétique)
        /** Share of annual income spent on housing energy, in whole percent. */
        public int $energyEffortPct,
        /** Above the ONPE threshold (8 %): the household is in fuel poverty. */
        public bool $inFuelPoverty,
        // Patrimoine (non-liquid, realisable on resale only — §8)
        public string $propertyValueLabel,
        /** Purchase price = the value floor, at the worst DPE class (G). */
        public string $propertyPurchaseLabel,
        /** DPE classes gained over G so far (0 = still a passoire). */
        public int $propertyClassesGained,
        /** Sourced "valeur verte" premium per class gained, in whole percent. */
        public int $propertyStepPct,
        /** Green-value gain over the purchase price so far, signed. */
        public string $propertyGreenValueLabel,
        // Loan (éco-PTZ account)
        public bool $loanActive,
        public string $loanMonthlyPaymentLabel,
        public string $loanRemainingLabel,
        /** Full duration of the éco-PTZ, in years (20). */
        public int $loanTermYears,
        /** Whole years of payments still ahead on the active loan. */
        public int $loanRemainingYears,
        // Heating & comfort
        public string $heatingLabel,
        /** The scripted breakdown happened and the boiler is still dead (no heating). */
        public bool $boilerBroken,
        // Thermostat setpoint
        public int $setpointC,
        public bool $setpointCanUp,
        public bool $setpointCanDown,
        /** Dialled below the OMS 18 °C health floor. */
        public bool $setpointBelowHealthy,
        /** Live preview of −1 °C on the yearly bill (empty at the lower bound). */
        public string $setpointDownEffectLabel,
        /** Live preview of +1 °C on the yearly bill (empty at the upper bound). */
        public string $setpointUpEffectLabel,
        public string $insulationLabel,
        /** Final DPE letter (the worse of the two labels below). */
        public string $dpeLetter,
        /** DPE energy label: letter, primary-energy intensity (kWhEP/m²/an), cursor position in the band. */
        public string $dpeEnergyLetter,
        public int $dpeEnergyIntensity,
        public int $dpeEnergyBandPct,
        /** DPE climate label: letter, emissions (kgCO₂/m²/an), cursor position in the band. */
        public string $dpeClimateLetter,
        public int $dpeClimateIntensity,
        public int $dpeClimateBandPct,
        public float $heatingElectricityKwh,
        public float $fuelOilLitres,
        /** Wood pellets burnt today, in kilograms (pellet boiler). */
        public float $pelletKg,
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
        /** Wood pellets burnt since day 1, in kilograms. */
        public float $totalPelletKg,
        public int $averageComfortPct,
        public string $totalElectricityCostLabel,
        public string $totalFuelOilCostLabel,
        public string $totalSurplusRevenueLabel,
        public string $totalNetEnergyCostLabel,
        /**
         * The lived carbon footprint since day 1 (fuel burnt + grid drawn),
         * pre-formatted with an adaptive unit ("1,2 t" / "845 kg"). Distinct
         * from the DPE climate label, which rates the building, not the year
         * actually played.
         */
        public string $co2EmittedLabel = '0 kg',
        // Envelope "done" flags (renovation tree)
        /** Attic/roof already insulated? */
        public bool $roofInsulated = false,
        /** Wall insulation done so far (ITI/ITE label), empty when walls are untreated. */
        public string $wallInsulationLabel = '',
        /** Current glazing tier label ('Simple vitrage'...). */
        public string $glazingLabel = '',
        /** Glazing already at its best tier (triple)? */
        public bool $glazingMaxed = false,
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
