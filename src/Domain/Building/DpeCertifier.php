<?php

declare(strict_types=1);

namespace App\Domain\Building;

use App\Domain\Energy\EnergyCalibration;

/**
 * Produces a {@see DpeAssessment} from a dwelling's annual energy use, applying
 * the official French DPE method (primary-energy and CO₂ intensities per m²,
 * class = worse of the two labels). Pure and deterministic; every coefficient
 * is sourced ({@see EnergyCalibration}, {@see BuildingCalibration}).
 */
final readonly class DpeCertifier
{
    public function __construct(
        private EnergyCalibration $energy = new EnergyCalibration(),
        private BuildingCalibration $building = new BuildingCalibration(),
    ) {
    }

    /**
     * @param float $electricityKwh year's electricity use (final energy)
     * @param float $fuelOilLitres  year's fuel-oil use (converted to energy here)
     */
    public function certify(float $electricityKwh, float $fuelOilLitres): DpeAssessment
    {
        $area = $this->building->referenceFloorAreaM2()->value;
        $fuelOilKwh = $fuelOilLitres * $this->energy->fuelOilEnergyKwhPerLitre()->value;

        // Energy label: primary energy per m². Electricity carries the 2.3
        // factor; fossil fuels convert at 1.0 (their final energy IS primary).
        $primaryFactor = $this->energy->electricityPrimaryEnergyFactor()->value;
        $energyIntensity = ($electricityKwh * $primaryFactor + $fuelOilKwh) / $area;

        // Climate label: grams of CO₂ → kilograms, per m².
        $climateIntensity = (
            $electricityKwh * $this->energy->electricityCo2GramsPerKwh()->value
            + $fuelOilKwh * $this->energy->fuelOilCo2GramsPerKwh()->value
        ) / 1000.0 / $area;

        $energyClass = DpeClass::fromEnergyIntensity($energyIntensity);
        $climateClass = DpeClass::fromClimateIntensity($climateIntensity);

        return new DpeAssessment(
            energyIntensity: $energyIntensity,
            energyClass: $energyClass,
            energyBandFillPct: DpeClass::fillPct($energyIntensity, $energyClass->energyBand()),
            climateIntensity: $climateIntensity,
            climateClass: $climateClass,
            climateBandFillPct: DpeClass::fillPct($climateIntensity, $climateClass->climateBand()),
            finalClass: DpeClass::worstOf($energyClass, $climateClass),
        );
    }
}
