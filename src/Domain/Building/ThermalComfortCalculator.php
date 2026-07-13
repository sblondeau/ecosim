<?php

declare(strict_types=1);

namespace App\Domain\Building;

use function max;
use function min;
use function round;

/**
 * Simulates the day's indoor and felt temperatures and scores the comfort
 * (game-design §8: thermalComfortScore, plage 19-26 °C, dégradation
 * progressive).
 *
 * Deliberately simple model, one assumption per line:
 * - during the heating season (outdoor below the degree-day base) the heating
 *   system holds the indoor air at the setpoint;
 * - without heating (boiler breakdown, étape 7) the house settles at its
 *   steady-state equilibrium: internal gains (appliances and occupants — the
 *   household's base electricity all ends up as heat indoors) divided by the
 *   envelope loss rate. No thermal-inertia buffer: the daily tick jumps
 *   straight to the worst-case equilibrium, which overstates the first day or
 *   two but never flatters the situation. Better insulation holds more of the
 *   gains — the same sourced coefficients as the heating need, no new number;
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

    public function comfortFor(InsulationLevel $insulation, float $outdoorC, ?float $setpointC = null): ThermalComfort
    {
        $setpoint = $setpointC ?? $this->calibration->heatingSetpointC()->value;
        $balancePoint = $setpoint - $this->calibration->internalHeatGainOffsetC()->value;

        // Heating holds the air at the setpoint whenever it is cold enough to
        // need it; otherwise the house free-runs at the outdoor mean.
        $indoor = $outdoorC < $balancePoint ? $setpoint : $outdoorC;

        return $this->comfortAt($insulation, $indoor, $outdoorC);
    }

    /**
     * Comfort with NO working heating: indoor air at the unheated equilibrium.
     *
     * @param float $internalGainsKwh heat dissipated indoors over the day (base
     *                                electricity use of the household), in kWh
     */
    public function unheatedComfortFor(InsulationLevel $insulation, float $outdoorC, float $internalGainsKwh): ThermalComfort
    {
        if ($outdoorC >= $this->calibration->heatingBaseTemperatureC()->value) {
            // Free-running season: no heating was needed anyway.
            return $this->comfortFor($insulation, $outdoorC);
        }

        $lossPerDegreeDay = $this->calibration->heatLossKwhPerDegreeDay()->value
            * $this->calibration->insulationFactor($insulation)->value;
        $gainC = max(0.0, $internalGainsKwh) / $lossPerDegreeDay;

        $indoor = min(
            $this->calibration->heatingSetpointC()->value,
            $outdoorC + $gainC,
        );

        return $this->comfortAt($insulation, $indoor, $outdoorC);
    }

    private function comfortAt(InsulationLevel $insulation, float $indoorC, float $outdoorC): ThermalComfort
    {
        $coldWallPenalty = $this->calibration->coldWallPenaltyFactor($insulation)->value
            * max(0.0, $indoorC - $outdoorC);
        $felt = round($indoorC - $coldWallPenalty, 1);

        return new ThermalComfort(
            indoorC: round($indoorC, 1),
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
