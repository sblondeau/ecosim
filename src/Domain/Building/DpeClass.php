<?php

declare(strict_types=1);

namespace App\Domain\Building;

use function max;
use function min;

/**
 * DPE class, A (best) to G (worst), with the two official French labels:
 * energy (kWhEP/m²/an) and climate (kgCO₂/m²/an). Since the 2021 method the
 * final class is the WORSE of the two, so electrification (low-carbon
 * electricity) and insulation move the two labels differently — a heat pump
 * collapses the climate label long before the energy one.
 *
 * The class is derived from the dwelling's real annual intensities
 * ({@see DpeCertifier}), not a lookup table: that is what
 * lets the UI place a cursor inside a band and show how close a home is to
 * tipping into the next letter (the bands are wide, so it is often meaningful).
 *
 * Thresholds are the official upper bounds of each class (arrêté du 3 avril
 * 2021). Solar self-consumption and the battery are NOT modelled here (the real
 * 3CL only deducts autoconsommation, negligible while heating is fuel-oil — see
 * docs/backlog.md, Phase 4 PropertyValuation).
 */
enum DpeClass: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case E = 'E';
    case F = 'F';
    case G = 'G';

    /** Upper display bound of G, past its 420 / 100 threshold — so the cursor has a scale. */
    private const float ENERGY_DISPLAY_MAX = 520.0;
    private const float CLIMATE_DISPLAY_MAX = 130.0;

    /** Energy label from primary-energy intensity, in kWhEP/m²/an (official thresholds). */
    public static function fromEnergyIntensity(float $kwhEpPerM2Year): self
    {
        return match (true) {
            $kwhEpPerM2Year <= 70.0 => self::A,
            $kwhEpPerM2Year <= 110.0 => self::B,
            $kwhEpPerM2Year <= 180.0 => self::C,
            $kwhEpPerM2Year <= 250.0 => self::D,
            $kwhEpPerM2Year <= 330.0 => self::E,
            $kwhEpPerM2Year <= 420.0 => self::F,
            default => self::G,
        };
    }

    /** Climate label from emission intensity, in kgCO₂/m²/an (official thresholds). */
    public static function fromClimateIntensity(float $kgCo2PerM2Year): self
    {
        return match (true) {
            $kgCo2PerM2Year <= 6.0 => self::A,
            $kgCo2PerM2Year <= 11.0 => self::B,
            $kgCo2PerM2Year <= 30.0 => self::C,
            $kgCo2PerM2Year <= 50.0 => self::D,
            $kgCo2PerM2Year <= 70.0 => self::E,
            $kgCo2PerM2Year <= 100.0 => self::F,
            default => self::G,
        };
    }

    /** The worse of two labels — the rule that sets the final DPE class since 2021. */
    public static function worstOf(self $a, self $b): self
    {
        return $a->stepsAboveWorst() <= $b->stepsAboveWorst() ? $a : $b;
    }

    /**
     * Where a value sits inside this class's band, 0 (just entered) to 100
     * (about to tip into the next, worse letter) — drives the position cursor.
     *
     * @param array{float, float} $band lower/upper bound of this class
     */
    public static function fillPct(float $value, array $band): float
    {
        [$lower, $upper] = $band;

        return max(0.0, min(100.0, ($value - $lower) / ($upper - $lower) * 100.0));
    }

    /**
     * Lower/upper bound of this class on the energy scale, kWhEP/m²/an
     * (G is capped at a display maximum so the cursor has somewhere to sit).
     *
     * @return array{float, float}
     */
    public function energyBand(): array
    {
        return match ($this) {
            self::A => [0.0, 70.0],
            self::B => [70.0, 110.0],
            self::C => [110.0, 180.0],
            self::D => [180.0, 250.0],
            self::E => [250.0, 330.0],
            self::F => [330.0, 420.0],
            self::G => [420.0, self::ENERGY_DISPLAY_MAX],
        };
    }

    /**
     * Lower/upper bound of this class on the climate scale, kgCO₂/m²/an.
     *
     * @return array{float, float}
     */
    public function climateBand(): array
    {
        return match ($this) {
            self::A => [0.0, 6.0],
            self::B => [6.0, 11.0],
            self::C => [11.0, 30.0],
            self::D => [30.0, 50.0],
            self::E => [50.0, 70.0],
            self::F => [70.0, 100.0],
            self::G => [100.0, self::CLIMATE_DISPLAY_MAX],
        };
    }

    public function label(): string
    {
        return $this->value;
    }

    /**
     * Number of classes above G (worst): G = 0, F = 1 … A = 6. Used by the
     * property-value formula (each class gained adds a sourced percentage).
     */
    public function stepsAboveWorst(): int
    {
        return match ($this) {
            self::G => 0,
            self::F => 1,
            self::E => 2,
            self::D => 3,
            self::C => 4,
            self::B => 5,
            self::A => 6,
        };
    }
}
