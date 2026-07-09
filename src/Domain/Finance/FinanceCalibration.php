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
            value: 2100.0,
            unit: '€/month',
            min: 1800.0,
            max: 2800.0,
            source: 'Calibration de jeu : reste-à-vivre serré du primo-accédant (~700 €/mois hors énergie, game-design §18) — un janvier au fioul (~740 €) le consomme entièrement',
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
     * Savings available at the start of the game. Balanced against the heat
     * pump's 7 800 € net cost around the scripted January 20th breakdown:
     * NOT cash-affordable on day 1 (7 750 < 7 800 — anticipating means the
     * loan), but with the ~700 € of January 1st net income minus ~19 days of
     * fuel-oil bills, the panne morning always leaves JUST enough to pay the
     * heat pump in cash — barely, wiping the account (measured over 500
     * weather seeds: 7 864-8 084 €). Repair (~1 500 €), prime and éco-PTZ
     * keep the other exits open; the choice at the breakdown is real.
     */
    public function startingSavings(): Coefficient
    {
        return new Coefficient(
            value: 7750.0,
            unit: '€',
            min: 5000.0,
            max: 12000.0,
            source: 'Banque de France / INSEE : épargne de précaution résiduelle d\'un primo-accédant juste après l\'achat (ordre de grandeur, scénario game-design §18)',
            reviewedOn: '2025-01-01',
        );
    }

    /** Heavy repair of the old fuel-oil boiler (burner, circulator, heat body). */
    public function boilerRepairCost(): Coefficient
    {
        return new Coefficient(
            value: 1500.0,
            unit: '€',
            min: 800.0,
            max: 3000.0,
            source: 'Fourchette artisans chauffagistes : réparation lourde d\'une chaudière fioul ancienne (brûleur, circulateur, corps de chauffe), 2024',
            reviewedOn: '2025-01-01',
        );
    }

    /** Intermediate insulation package (attic + walls), Original -> Retrofitted. */
    public function insulationRetrofitCost(): Coefficient
    {
        return new Coefficient(
            value: 15000.0,
            unit: '€',
            min: 10000.0,
            max: 20000.0,
            source: 'ADEME : bouquet isolation intermédiaire (combles + murs), maison ~100 m²',
            reviewedOn: '2025-01-01',
        );
    }

    /** Full-performance insulation (BBC-réno level), Retrofitted -> Reinforced. */
    public function insulationReinforceCost(): Coefficient
    {
        return new Coefficient(
            value: 25000.0,
            unit: '€',
            min: 18000.0,
            max: 35000.0,
            source: 'ADEME : rénovation globale performante (niveau BBC réno), complément maison ~100 m²',
            reviewedOn: '2025-01-01',
        );
    }

    /** Air/water heat pump, installed (replacing the fuel-oil boiler). */
    public function heatPumpInstallCost(): Coefficient
    {
        return new Coefficient(
            value: 13000.0,
            unit: '€',
            min: 10000.0,
            max: 16000.0,
            source: 'ADEME : PAC air/eau posée, maison individuelle ~100 m² (dépose chaudière incluse)',
            reviewedOn: '2025-01-01',
        );
    }

    /** Rooftop solar installation (the single 3 kWc catalogue model), installed. */
    public function solarInstallCost(): Coefficient
    {
        return new Coefficient(
            value: 7500.0,
            unit: '€',
            min: 6000.0,
            max: 9000.0,
            source: 'ADEME / observatoires du marché PV : installation résidentielle 3 kWc clé en main, 2024',
            reviewedOn: '2025-01-01',
        );
    }

    /** Home battery (the single 5 kWh catalogue model), installed. */
    public function batteryInstallCost(): Coefficient
    {
        return new Coefficient(
            value: 5000.0,
            unit: '€',
            min: 4000.0,
            max: 7000.0,
            source: 'Marché du stockage résidentiel : batterie ~5 kWh posée, 2024 (~800-1200 €/kWh)',
            reviewedOn: '2025-01-01',
        );
    }

    /** Absolute cap on the renovation prime. */
    public function subsidyCap(): Coefficient
    {
        return new Coefficient(
            value: 15000.0,
            unit: '€',
            min: 10000.0,
            max: 20000.0,
            source: 'Esprit MaPrimeRénov\' : plafond d\'aide par geste/bouquet (simplifié, game-design §18)',
            reviewedOn: '2025-01-01',
        );
    }

    /** Écrêtement: the prime never covers more than this share of the cost. */
    public function subsidyMaxShare(): Coefficient
    {
        return new Coefficient(
            value: 0.9,
            unit: 'fraction',
            min: 0.8,
            max: 1.0,
            source: 'MaPrimeRénov\' : écrêtement, reste à charge minimum toujours dû (game-design §8)',
            reviewedOn: '2025-01-01',
        );
    }

    /** Regulatory cap of the zero-interest loan (total borrowed). */
    public function loanCap(): Coefficient
    {
        return new Coefficient(
            value: 50000.0,
            unit: '€',
            min: 50000.0,
            max: 50000.0,
            source: 'Éco-PTZ : plafond réglementaire de 50 000 € (game-design §8)',
            reviewedOn: '2025-01-01',
        );
    }

    /** Annual income ceiling of the "très modeste" prime bracket (couple, hors IdF). */
    public function veryModestIncomeCeiling(): Coefficient
    {
        return new Coefficient(value: 20000.0, unit: '€/an', min: 15000.0, max: 23000.0, source: 'Esprit MaPrimeRénov\' (barème bleu, couple hors IdF)', reviewedOn: '2025-01-01');
    }

    /** Prime rate of the "très modeste" bracket. */
    public function veryModestSubsidyRate(): Coefficient
    {
        return new Coefficient(value: 0.8, unit: 'fraction', min: 0.7, max: 0.9, source: 'Game-design §8 : jusqu\'à 80 % pour les très modestes (MaPrimeRénov\' bleu)', reviewedOn: '2025-01-01');
    }

    /** Annual income ceiling of the "modeste" bracket. */
    public function modestIncomeCeiling(): Coefficient
    {
        return new Coefficient(value: 27000.0, unit: '€/an', min: 23000.0, max: 30000.0, source: 'Esprit MaPrimeRénov\' (barème jaune, couple hors IdF)', reviewedOn: '2025-01-01');
    }

    /** Prime rate of the "modeste" bracket. */
    public function modestSubsidyRate(): Coefficient
    {
        return new Coefficient(value: 0.6, unit: 'fraction', min: 0.5, max: 0.7, source: 'Esprit MaPrimeRénov\' (barème jaune)', reviewedOn: '2025-01-01');
    }

    /** Annual income ceiling of the "intermédiaire" bracket. */
    public function intermediateIncomeCeiling(): Coefficient
    {
        return new Coefficient(value: 38000.0, unit: '€/an', min: 34000.0, max: 42000.0, source: 'Esprit MaPrimeRénov\' (barème violet, couple hors IdF)', reviewedOn: '2025-01-01');
    }

    /** Prime rate of the "intermédiaire" bracket — the scenario household lands here. */
    public function intermediateSubsidyRate(): Coefficient
    {
        return new Coefficient(value: 0.4, unit: 'fraction', min: 0.3, max: 0.5, source: 'Esprit MaPrimeRénov\' (barème violet)', reviewedOn: '2025-01-01');
    }

    /** Prime rate above the last ceiling ("supérieur"). */
    public function upperSubsidyRate(): Coefficient
    {
        return new Coefficient(value: 0.2, unit: 'fraction', min: 0.1, max: 0.3, source: 'Esprit MaPrimeRénov\' (barème rose)', reviewedOn: '2025-01-01');
    }
}
