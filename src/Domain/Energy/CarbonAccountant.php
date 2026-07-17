<?php

declare(strict_types=1);

namespace App\Domain\Energy;

/**
 * Turns real energy flows into the CO₂ actually emitted (game-design §1, §13).
 *
 * This is the *lived footprint* — what the household truly put in the air by
 * burning fuel oil and drawing grid electricity — as opposed to the DPE climate
 * label ({@see \App\Domain\Building\DpeCertifier}), which is a standardised
 * *rating* of the building (kgCO₂/m²/an over a reference year). Both use the
 * same sourced emission factors ({@see EnergyCalibration}), so the running
 * counter and the label tell one coherent story.
 *
 * Self-consumed solar emits nothing, so only the electricity actually imported
 * from the grid counts — this is the honest boundary for "what you emitted".
 */
final readonly class CarbonAccountant
{
    public function __construct(
        private EnergyCalibration $energy = new EnergyCalibration(),
    ) {
    }

    /**
     * CO₂ emitted, in kilograms, by burning $fuelOilLitres of fuel oil,
     * burning $pelletKg of wood pellets, and drawing $gridImportKwh from the
     * grid.
     */
    public function emittedKg(float $fuelOilLitres, float $gridImportKwh, float $pelletKg = 0.0): float
    {
        $fuelKwh = $fuelOilLitres * $this->energy->fuelOilEnergyKwhPerLitre()->value;
        $pelletKwh = $pelletKg * $this->energy->pelletEnergyKwhPerKg()->value;

        $grams = $fuelKwh * $this->energy->fuelOilCo2GramsPerKwh()->value
            + $gridImportKwh * $this->energy->electricityCo2GramsPerKwh()->value
            + $pelletKwh * $this->energy->pelletCo2GramsPerKwh()->value;

        return $grams / 1000.0;
    }
}
