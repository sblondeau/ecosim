<?php

declare(strict_types=1);

namespace App\Domain\Building;

use App\Domain\Calibration\Coefficient;

/**
 * Sourced coefficients for the Phase 0-1 building model: heating need,
 * heating-system conversion and thermal comfort (game-design §8, §12, §15).
 *
 * The heating-need model is the standard degree-day method: daily need =
 * heat-loss rate × insulation factor × max(0, base − outdoor). Calibrated for
 * the scenario's house — an old ~100 m² DPE F-G home (game-design §15).
 * Every number is a {@see Coefficient} (§13); nothing inlined.
 */
final class BuildingCalibration
{
    /**
     * Base temperature of the degree-day method: heating is needed on days
     * whose mean outdoor temperature falls below it.
     */
    public function heatingBaseTemperatureC(): Coefficient
    {
        return new Coefficient(
            value: 18.0,
            unit: '°C',
            min: 17.0,
            max: 19.0,
            source: 'Convention des degrés-jours unifiés (DJU) base 18 °C — Météo-France / COSTIC',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Indoor temperature the heating system maintains during the heating season.
     */
    public function heatingSetpointC(): Coefficient
    {
        return new Coefficient(
            value: 19.0,
            unit: '°C',
            min: 18.0,
            max: 21.0,
            source: 'Code de l\'énergie, art. R241-26 : limite de chauffage de 19 °C en période d\'occupation',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Heat loss of the UNinsulated scenario house, per degree-day.
     */
    public function heatLossKwhPerDegreeDay(): Coefficient
    {
        return new Coefficient(
            value: 12.5,
            unit: 'kWh/(°C·day)',
            min: 9.0,
            max: 15.0,
            source: 'Dérivé ADEME/DPE : passoire thermique ~300 kWh/m²/an de chauffage × 100 m² / ~2400 DJU base 18 (France)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * How much of the reference heat loss remains at a given insulation tier
     * (1.0 = the uninsulated starting house).
     */
    public function insulationFactor(InsulationLevel $level): Coefficient
    {
        return match ($level) {
            InsulationLevel::None => new Coefficient(
                value: 1.0,
                unit: 'fraction',
                min: 1.0,
                max: 1.0,
                source: 'Référence : état de départ du scénario (passoire non isolée, game-design §15)',
                reviewedOn: '2025-01-01',
            ),
            InsulationLevel::Retrofitted => new Coefficient(
                value: 0.55,
                unit: 'fraction',
                min: 0.45,
                max: 0.65,
                source: 'ADEME : rénovation intermédiaire (combles + murs), −35 à −55 % de besoin de chauffage',
                reviewedOn: '2025-01-01',
            ),
            InsulationLevel::Reinforced => new Coefficient(
                value: 0.30,
                unit: 'fraction',
                min: 0.20,
                max: 0.40,
                source: 'ADEME : rénovation globale performante (niveau BBC rénovation), −60 à −80 % de besoin',
                reviewedOn: '2025-01-01',
            ),
        };
    }

    /**
     * Seasonal efficiency of the scenario's ageing fuel-oil boiler.
     */
    public function fuelOilBoilerEfficiency(): Coefficient
    {
        return new Coefficient(
            value: 0.85,
            unit: 'fraction',
            min: 0.75,
            max: 0.92,
            source: 'ADEME : rendement saisonnier d\'une chaudière fioul ancienne génération',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Energy content of domestic fuel oil (net calorific value).
     */
    public function fuelOilEnergyKwhPerLitre(): Coefficient
    {
        return new Coefficient(
            value: 9.96,
            unit: 'kWh/L',
            min: 9.8,
            max: 10.1,
            source: 'PCI du fioul domestique ≈ 9,96 kWh/litre (valeur réglementaire française)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Seasonal COP of the single Phase 0-1 heat-pump model, as measured in
     * real conditions (not the optimistic nameplate figure).
     */
    public function heatPumpScop(): Coefficient
    {
        return new Coefficient(
            value: 3.5,
            unit: 'ratio',
            min: 2.9,
            max: 4.3,
            source: 'ADEME : SCOP mesuré en conditions réelles, PAC air/eau (game-design §12)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Cold-wall discomfort: fraction of the indoor/outdoor gap subtracted from
     * the felt temperature (poorly insulated walls radiate cold even when the
     * air is at the setpoint). ~0.15 × 19 °C gap ≈ 3 °C felt loss in a
     * passoire on a freezing day.
     */
    public function coldWallPenaltyFactor(InsulationLevel $level): Coefficient
    {
        return match ($level) {
            InsulationLevel::None => new Coefficient(
                value: 0.15,
                unit: 'fraction',
                min: 0.10,
                max: 0.20,
                source: 'ADEME : effet parois froides, 1 à 3 °C de température ressentie en moins dans un logement mal isolé',
                reviewedOn: '2025-01-01',
            ),
            InsulationLevel::Retrofitted => new Coefficient(
                value: 0.07,
                unit: 'fraction',
                min: 0.04,
                max: 0.10,
                source: 'ADEME : effet parois froides réduit après isolation des parois principales',
                reviewedOn: '2025-01-01',
            ),
            InsulationLevel::Reinforced => new Coefficient(
                value: 0.03,
                unit: 'fraction',
                min: 0.01,
                max: 0.05,
                source: 'ADEME : parois isolées performantes, effet ressenti quasi nul',
                reviewedOn: '2025-01-01',
            ),
        };
    }

    /**
     * Lower bound of the comfort range (below it, comfort degrades).
     */
    public function comfortMinC(): Coefficient
    {
        return new Coefficient(
            value: 19.0,
            unit: '°C',
            min: 18.0,
            max: 20.0,
            source: 'Plage de confort thermique 19-26 °C (game-design §8, repères ADEME)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Upper bound of the comfort range (above it, comfort degrades).
     */
    public function comfortMaxC(): Coefficient
    {
        return new Coefficient(
            value: 26.0,
            unit: '°C',
            min: 25.0,
            max: 28.0,
            source: 'Plage de confort thermique 19-26 °C (game-design §8, repères ADEME)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Comfort points lost per °C of felt temperature outside the range.
     */
    public function comfortLossPerDegree(): Coefficient
    {
        return new Coefficient(
            value: 10.0,
            unit: 'points/°C',
            min: 5.0,
            max: 15.0,
            source: 'Calibration de jeu : dégradation progressive hors plage (game-design §8)',
            reviewedOn: '2025-01-01',
        );
    }
}
