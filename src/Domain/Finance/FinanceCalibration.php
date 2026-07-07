<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Calibration\Coefficient;

/**
 * Sourced coefficients for the Phase 0-1 household finances (game-design §8,
 * §15): fixed energy tariffs, the surplus-resale contract, and the household
 * budget profile of the locked scenario (primo-accédant couple).
 *
 * Tariffs are FIXED for the whole game — no price evolution in this phase
 * (§15). The wide gap between the grid purchase price and the surplus resale
 * price is deliberate and real (§8): self-consumed kWh are worth ~20× exported
 * ones, which is what makes sizing-to-need and the battery meaningful.
 */
final class FinanceCalibration
{
    /**
     * Grid electricity purchase price (all taxes included).
     */
    public function electricityPricePerKwh(): Coefficient
    {
        return new Coefficient(
            value: 0.22,
            unit: '€/kWh',
            min: 0.19,
            max: 0.25,
            source: 'CRE : tarif réglementé de vente (option base), fourchette 2024-2026 (game-design §8 : 19-25 c€/kWh)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Surplus resale price, fixed at contract signature (EDF OA-like).
     */
    public function surplusSellPricePerKwh(): Coefficient
    {
        return new Coefficient(
            value: 0.011,
            unit: '€/kWh',
            min: 0.01,
            max: 0.04,
            source: 'CRE / EDF OA : tarif de rachat du surplus en autoconsommation, ~1,1 c€/kWh mi-2026 (game-design §8)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Domestic fuel-oil price per litre (delivered).
     */
    public function fuelOilPricePerLitre(): Coefficient
    {
        return new Coefficient(
            value: 1.20,
            unit: '€/L',
            min: 1.00,
            max: 1.40,
            source: 'DGEC / ministère de la Transition énergétique : prix moyen du fioul domestique 2024-2025',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Net monthly income of the scenario household (young couple, modest).
     */
    public function monthlyNetIncome(): Coefficient
    {
        return new Coefficient(
            value: 2800.0,
            unit: '€/month',
            min: 2200.0,
            max: 3500.0,
            source: 'INSEE : niveau de vie d\'un jeune couple actif modeste (ordre de grandeur, scénario game-design §18)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Monthly living expenses EXCLUDING energy (food, transport, mortgage…).
     * Energy is billed separately by the simulation — that is the point.
     */
    public function monthlyLivingExpenses(): Coefficient
    {
        return new Coefficient(
            value: 2300.0,
            unit: '€/month',
            min: 1800.0,
            max: 2800.0,
            source: 'Calibration de jeu : reste-à-vivre serré du primo-accédant (~500 €/mois hors énergie, game-design §18)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Purchase price of the scenario house — bought AS a G passoire (the
     * passoire discount is already priced in, game-design §8: décote ~15 %).
     */
    public function housePurchasePrice(): Coefficient
    {
        return new Coefficient(
            value: 200000.0,
            unit: '€',
            min: 150000.0,
            max: 260000.0,
            source: 'Notaires de France : prix médian d\'une maison ancienne hors grandes métropoles (ordre de grandeur 2024)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Property-value gain per DPE class gained, for a house.
     */
    public function dpeClassValueStep(): Coefficient
    {
        return new Coefficient(
            value: 0.08,
            unit: 'fraction/classe',
            min: 0.04,
            max: 0.10,
            source: 'Notaires de France : impact du DPE sur la valeur, ~+8 %/classe pour une maison (game-design §8)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Savings available at the start of the game — deliberately NOT enough to
     * pay for a full renovation out of pocket: the prime and the zero-interest
     * loan (later bricks) are what unlock the big decisions.
     */
    public function startingSavings(): Coefficient
    {
        return new Coefficient(
            value: 8000.0,
            unit: '€',
            min: 5000.0,
            max: 15000.0,
            source: 'Calibration de jeu : épargne résiduelle après achat immobilier (scénario primo-accédant, game-design §18)',
            reviewedOn: '2025-01-01',
        );
    }
}
