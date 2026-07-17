<?php

declare(strict_types=1);

namespace App\Domain\Building;

use App\Domain\Calibration\Coefficient;

use function max;
use function round;

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

    // --- Enveloppe par surfaces (Tranche 1) : chaque surface traitée retire une
    // fraction de la déperdition TOTALE (part ADEME du poste × réduction obtenue). ---

    /** Combles isolés : toiture ~28 % des pertes × réduction ~85 %. */
    public function roofInsulationLossReduction(): Coefficient
    {
        return new Coefficient(value: 0.24, unit: 'fraction', min: 0.20, max: 0.28, source: 'ADEME : toiture ~25-30 % des déperditions × gain isolation combles ~80-90 %', reviewedOn: '2026-07-16');
    }

    /** Murs ITI : murs ~23 % des pertes × réduction ~70 % (ponts thermiques résiduels). */
    public function wallInteriorLossReduction(): Coefficient
    {
        return new Coefficient(value: 0.16, unit: 'fraction', min: 0.13, max: 0.19, source: 'ADEME : murs ~20-25 % des déperditions × gain ITI ~65-75 %', reviewedOn: '2026-07-16');
    }

    /** Murs ITE : idem mais réduction ~80 % (pas de pont thermique). */
    public function wallExteriorLossReduction(): Coefficient
    {
        return new Coefficient(value: 0.18, unit: 'fraction', min: 0.15, max: 0.21, source: 'ADEME : murs ~20-25 % des déperditions × gain ITE ~75-85 % (sans pont thermique)', reviewedOn: '2026-07-16');
    }

    /** Double vitrage : fenêtres ~13 % des pertes × réduction ~50 % vs simple. */
    public function doubleGlazingLossReduction(): Coefficient
    {
        return new Coefficient(value: 0.065, unit: 'fraction', min: 0.05, max: 0.08, source: 'ADEME : fenêtres ~10-15 % des déperditions × gain double vitrage ~50 %', reviewedOn: '2026-07-16');
    }

    /** Triple vitrage : réduction ~62 % (rendement décroissant vs double). */
    public function tripleGlazingLossReduction(): Coefficient
    {
        return new Coefficient(value: 0.08, unit: 'fraction', min: 0.06, max: 0.10, source: 'ADEME : fenêtres ~10-15 % × gain triple vitrage ~60 % (rendement décroissant)', reviewedOn: '2026-07-16');
    }

    /** VMC double flux : récupère la chaleur de l'air extrait (le renouvellement d'air ~20 % des pertes, récup ~70 %). */
    public function ventilationHeatRecoveryLossReduction(): Coefficient
    {
        return new Coefficient(value: 0.14, unit: 'fraction', min: 0.10, max: 0.18, source: 'ADEME : VMC double flux, récupération ~70-90 % sur le renouvellement d\'air (~20 % des déperditions)', reviewedOn: '2026-07-17');
    }

    /** Plancher du facteur de déperdition (au-delà, l'enveloppe seule ne descend pas — plancher/étanchéité, phases suivantes). */
    public function envelopeLossFloor(): Coefficient
    {
        return new Coefficient(value: 0.15, unit: 'fraction', min: 0.10, max: 0.20, source: 'Calibration de jeu : plancher physique, l\'enveloppe seule ne fait pas un BBC (résiduel plancher/ponts/étanchéité ; la ventilation est désormais modélisée)', reviewedOn: '2026-07-16');
    }

    /** Calfeutrage / joints : coupe les courants d'air. Petit geste — quelques % des pertes (vs combles 24 %). */
    public function draughtProofingLossReduction(): Coefficient
    {
        return new Coefficient(value: 0.04, unit: 'fraction', min: 0.02, max: 0.06, source: 'ADEME : fuites d\'air ~20 % des déperditions cumulées ; calfeutrage/joints de base = quelques %', reviewedOn: '2026-07-17');
    }

    /** Rideaux thermiques : coupent le rayonnement froid des fenêtres la nuit. Petit gain de ressenti. */
    public function thermalCurtainsColdWallRelief(): Coefficient
    {
        return new Coefficient(value: 0.02, unit: 'fraction', min: 0.01, max: 0.03, source: 'ADEME : rideaux thermiques, petit gain de température ressentie près des vitres', reviewedOn: '2026-07-17');
    }

    /**
     * Fraction de la déperdition d'origine qui subsiste, agrégée depuis les
     * surfaces traitées. 1,0 = maison d'origine (référence, DPE inchangé).
     */
    public function envelopeLossFactor(EnvelopeState $envelope): float
    {
        $removed = 0.0;

        if ($envelope->roofInsulated) {
            $removed += $this->roofInsulationLossReduction()->value;
        }

        $removed += match ($envelope->walls) {
            WallInsulation::None => 0.0,
            WallInsulation::Interior => $this->wallInteriorLossReduction()->value,
            WallInsulation::Exterior => $this->wallExteriorLossReduction()->value,
        };

        $removed += match ($envelope->glazing) {
            Glazing::Single => 0.0,
            Glazing::Double => $this->doubleGlazingLossReduction()->value,
            Glazing::Triple => $this->tripleGlazingLossReduction()->value,
        };

        $removed += Ventilation::DoubleFlow === $envelope->ventilation ? $this->ventilationHeatRecoveryLossReduction()->value : 0.0;

        $removed += $envelope->draughtProofed ? $this->draughtProofingLossReduction()->value : 0.0;

        // Rounded to 6 decimals: the sourced coefficients carry at most 3
        // significant decimals, so this only clears binary floating-point
        // noise (e.g. 0.24 + 0.16 + 0.065 landing a hair below 0.535) —
        // never a real precision loss.
        return max($this->envelopeLossFloor()->value, round(1.0 - $removed, 6));
    }

    /**
     * Above this residual loss factor, the house is "peu isolée" for advice
     * purposes: an air/water heat pump would be oversized, and glazing is a
     * low-priority spend. Game calibration (not a physical constant): 0.70
     * means combles + murs not yet done (combles seul = 0,76 → encore peu isolé).
     */
    public function poorlyInsulatedEnvelopeCeiling(): Coefficient
    {
        return new Coefficient(value: 0.70, unit: 'fraction', min: 0.60, max: 0.80, source: 'Calibration de jeu : seuil de conseil « maison peu isolée » (repères ADEME : isoler avant de dimensionner une PAC)', reviewedOn: '2026-07-16');
    }

    // --- Confort : effet paroi froide, dominé par murs + vitrages (pas les combles). ---

    /** Réduction de la pénalité paroi froide quand les murs sont isolés. */
    public function wallColdWallRelief(): Coefficient
    {
        return new Coefficient(value: 0.08, unit: 'fraction', min: 0.05, max: 0.10, source: 'ADEME : effet parois froides, les murs sont la principale surface rayonnante', reviewedOn: '2026-07-16');
    }

    public function doubleGlazingColdWallRelief(): Coefficient
    {
        return new Coefficient(value: 0.04, unit: 'fraction', min: 0.02, max: 0.06, source: 'ADEME : vitrage isolant, réduction du rayonnement froid des fenêtres', reviewedOn: '2026-07-16');
    }

    public function tripleGlazingColdWallRelief(): Coefficient
    {
        return new Coefficient(value: 0.05, unit: 'fraction', min: 0.03, max: 0.07, source: 'ADEME : triple vitrage, rayonnement froid quasi nul', reviewedOn: '2026-07-16');
    }

    public function roofColdWallRelief(): Coefficient
    {
        return new Coefficient(value: 0.01, unit: 'fraction', min: 0.00, max: 0.02, source: 'ADEME : le plafond contribue peu à l\'effet paroi froide ressenti', reviewedOn: '2026-07-16');
    }

    /** Base de la pénalité paroi froide (maison d'origine). Planché de la pénalité résiduelle. */
    public function baseColdWallPenaltyFactor(): Coefficient
    {
        return new Coefficient(value: 0.15, unit: 'fraction', min: 0.10, max: 0.20, source: 'ADEME : effet parois froides, 1 à 3 °C de ressenti en moins dans un logement mal isolé', reviewedOn: '2026-07-16');
    }

    public function coldWallPenaltyFloor(): Coefficient
    {
        return new Coefficient(value: 0.02, unit: 'fraction', min: 0.01, max: 0.03, source: 'ADEME : parois performantes, effet ressenti quasi nul (résiduel)', reviewedOn: '2026-07-16');
    }

    /** Fraction de l'écart intérieur/extérieur retirée au ressenti (parois froides), par surfaces. */
    public function coldWallPenaltyFactor(EnvelopeState $envelope): float
    {
        $penalty = $this->baseColdWallPenaltyFactor()->value;

        if ($envelope->roofInsulated) {
            $penalty -= $this->roofColdWallRelief()->value;
        }

        if (WallInsulation::None !== $envelope->walls) {
            $penalty -= $this->wallColdWallRelief()->value;
        }

        $penalty -= match ($envelope->glazing) {
            Glazing::Single => 0.0,
            Glazing::Double => $this->doubleGlazingColdWallRelief()->value,
            Glazing::Triple => $this->tripleGlazingColdWallRelief()->value,
        };

        if ($envelope->thermalCurtains) {
            $penalty -= $this->thermalCurtainsColdWallRelief()->value;
        }

        return max($this->coldWallPenaltyFloor()->value, $penalty);
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
