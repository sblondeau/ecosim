<?php

declare(strict_types=1);

namespace App\Domain\Building;

use App\Domain\Calibration\Coefficient;

/**
 * Sourced coefficients for the Phase 0-1 building model: what is BUILT —
 * envelope heat loss, indoor temperatures, cold walls, comfort (game-design
 * §8, §15). Heating-equipment characteristics (boiler efficiency, fuel energy
 * content, heat-pump SCOP) are energy-conversion facts and live in
 * {@see \App\Domain\Energy\EnergyCalibration}.
 *
 * The heating-need model is the standard degree-day method: daily need =
 * heat-loss rate × insulation factor × max(0, base − outdoor). Calibrated for
 * the scenario's house — an old ~100 m² DPE F-G home (game-design §15): the
 * house size and openings are bundled INTO these per-house coefficients (one
 * single house in this phase), not parameters yet.
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
     * How much free internal heat (occupants, appliances, solar gains) raises
     * the indoor temperature above what the heating alone would give — the gap
     * between the comfort setpoint and the degree-day balance point. At the
     * default 19 °C setpoint this yields the conventional 18 °C DJU base.
     */
    public function internalHeatGainOffsetC(): Coefficient
    {
        return new Coefficient(
            value: 1.0,
            unit: '°C',
            min: 0.5,
            max: 2.0,
            source: 'Convention DJU : apports internes/solaires gratuits ~1 °C (base 18 pour consigne 19), ADEME / COSTIC',
            reviewedOn: '2025-01-01',
        );
    }

    /** Lowest heating setpoint the player can dial (below is survival, not a choice). */
    public function minHeatingSetpointC(): Coefficient
    {
        return new Coefficient(value: 16.0, unit: '°C', min: 14.0, max: 17.0, source: 'Repère chauffage réduit / absence courte (Code de l\'énergie R241-26)', reviewedOn: '2025-01-01');
    }

    /** Highest sensible heating setpoint (above, comfort plateaus and the bill soars). */
    public function maxHeatingSetpointC(): Coefficient
    {
        return new Coefficient(value: 23.0, unit: '°C', min: 22.0, max: 24.0, source: 'Calibration de jeu : au-delà, confort en plateau et surconsommation (repères ADEME)', reviewedOn: '2025-01-01');
    }

    /** Health floor: heating below this exposes occupants to cold-related risks. */
    public function healthySetpointFloorC(): Coefficient
    {
        return new Coefficient(value: 18.0, unit: '°C', min: 18.0, max: 20.0, source: 'OMS, Housing and Health Guidelines 2018 : minimum 18 °C (20 °C pour les personnes vulnérables)', reviewedOn: '2025-01-01');
    }

    /**
     * Indoor target while the heating system is DOWN and portable electric
     * heaters take over (survival mode): households heat the living rooms to
     * a reduced setpoint, not to full comfort.
     */
    public function emergencySetpointC(): Coefficient
    {
        return new Coefficient(
            value: 16.0,
            unit: '°C',
            min: 14.0,
            max: 17.0,
            source: 'Code de l\'énergie, art. R241-26 : 16 °C, palier réglementaire d\'absence courte — repère du chauffage réduit',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Habitable floor area of the scenario house — the denominator of the DPE
     * intensities (kWhEP/m²/an and kgCO₂/m²/an). One fixed house this phase, so
     * it is a single reference value; when several dwellings exist it becomes a
     * per-house parameter (see docs/backlog.md, "taille du logement").
     */
    public function referenceFloorAreaM2(): Coefficient
    {
        return new Coefficient(
            value: 100.0,
            unit: 'm²',
            min: 90.0,
            max: 110.0,
            source: 'Scénario primo-accédant : maison ancienne ~100 m² (game-design §15)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Heat loss of the unrenovated scenario house, per degree-day.
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
     * How much of the reference heat loss remains at a given insulation tier.
     * 1.0 is the unrenovated original house — the calibration reference, not
     * "zero insulation" (walls always resist a little; that residual
     * resistance is already inside heatLossKwhPerDegreeDay).
     */
    public function insulationFactor(InsulationLevel $level): Coefficient
    {
        return match ($level) {
            InsulationLevel::Original => new Coefficient(
                value: 1.0,
                unit: 'fraction',
                min: 1.0,
                max: 1.0,
                source: 'Référence : état de départ du scénario (isolation d\'origine, game-design §15)',
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
     * Cold-wall discomfort: fraction of the indoor/outdoor gap subtracted from
     * the felt temperature (poorly insulated walls radiate cold even when the
     * air is at the setpoint). ~0.15 × 19 °C gap ≈ 3 °C felt loss in a
     * passoire on a freezing day.
     */
    public function coldWallPenaltyFactor(InsulationLevel $level): Coefficient
    {
        return match ($level) {
            InsulationLevel::Original => new Coefficient(
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
