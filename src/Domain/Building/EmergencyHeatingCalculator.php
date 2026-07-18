<?php

declare(strict_types=1);

namespace App\Domain\Building;

use App\Domain\Energy\EnergyCalibration;

use function max;
use function min;
use function round;

/**
 * The household's forced fallback while the boiler is dead: portable electric
 * heaters, automatically on — nobody lives at 4 °C in January, so this is not
 * a player choice (playtest decision). Direct Joule heating (1 kWh in = 1 kWh
 * out) aiming at a reduced survival setpoint, capped by the heaters' output.
 *
 * Consequences fall out of the physics, no scripted malus needed: the
 * electricity line of the bill explodes (the §12 lesson in reverse — this is
 * the worst heating there is), and on cold days the heaters cannot even hold
 * the reduced setpoint. Deciding fast is worth real money AND comfort.
 */
final readonly class EmergencyHeatingCalculator
{
    public function __construct(
        private BuildingCalibration $building = new BuildingCalibration(),
        private EnergyCalibration $energy = new EnergyCalibration(),
    ) {
    }

    /**
     * @param float $internalGainsKwh heat already dissipated indoors over the
     *                                day (base electricity use), in kWh
     */
    public function consumptionFor(EnvelopeState $envelope, float $outdoorC, float $internalGainsKwh): HeatingConsumption
    {
        $lossPerDegree = $this->building->heatLossKwhPerDegreeDay()->value
            * $this->building->envelopeLossFactor($envelope);

        // Heat required to hold the survival setpoint, net of free gains.
        $wanted = max(
            0.0,
            $lossPerDegree * ($this->building->emergencySetpointC()->value - $outdoorC) - $internalGainsKwh,
        );

        $delivered = min($wanted, $this->energy->emergencyHeatersMaxKwhPerDay()->value);

        return new HeatingConsumption(
            needKwh: round($wanted, 2),
            electricityKwh: round($delivered, 2),
            fuelOilLitres: 0.0,
        );
    }
}
