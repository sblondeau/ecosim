<?php

declare(strict_types=1);

namespace App\Domain\Building;

use function max;
use function round;

/**
 * Simulates the day's indoor and felt temperatures and scores the comfort
 * (game-design §8: thermalComfortScore, plage 19-26 °C, dégradation
 * progressive).
 *
 * Deliberately simple model, one assumption per line:
 * - during the heating season (outdoor below the degree-day base) the heating
 *   system holds the indoor air at the setpoint — undersized/broken heating
 *   arrives with the boiler-breakdown event (étape 7);
 * - outside the heating season the house free-runs at the outdoor mean (no
 *   cooling in the Phase 0-1 scope, no heatwaves in the weather model yet);
 * - insulation shows up as the cold-wall effect: the felt temperature drops by
 *   a fraction of the indoor/outdoor gap (sourced in
 *   {@see BuildingCalibration::coldWallPenaltyFactor()}) — the pedagogical
 *   point that insulation buys comfort, not just smaller bills (§8).
 */
final readonly class ThermalComfortCalculator
{
    public function __construct(
        private BuildingCalibration $calibration = new BuildingCalibration(),
    ) {
    }

    public function comfortFor(InsulationLevel $insulation, float $outdoorC): ThermalComfort
    {
        $indoor = $outdoorC < $this->calibration->heatingBaseTemperatureC()->value
            ? $this->calibration->heatingSetpointC()->value
            : $outdoorC;

        $coldWallPenalty = $this->calibration->coldWallPenaltyFactor($insulation)->value
            * max(0.0, $indoor - $outdoorC);
        $felt = round($indoor - $coldWallPenalty, 1);

        return new ThermalComfort(
            indoorC: round($indoor, 1),
            feltC: $felt,
            score: $this->score($felt),
        );
    }

    /**
     * 100 inside the comfort range, minus N points per °C of felt temperature
     * outside it, floored at 0.
     */
    private function score(float $feltC): int
    {
        $belowRange = max(0.0, $this->calibration->comfortMinC()->value - $feltC);
        $aboveRange = max(0.0, $feltC - $this->calibration->comfortMaxC()->value);
        $degreesOutside = $belowRange + $aboveRange;

        $score = 100.0 - $this->calibration->comfortLossPerDegree()->value * $degreesOutside;

        return (int) round(max(0.0, $score));
    }
}
