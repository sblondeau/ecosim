<?php

declare(strict_types=1);

namespace App\Domain\Energy;

use App\Domain\Calibration\Coefficient;

use function sqrt;

/**
 * Sourced coefficients for the Phase 0-1 energy loop: solar production, base
 * household electricity demand, and battery storage (game-design §3, §8, §15).
 *
 * Values are calibrated to a French residential setting (PVGIS / ADEME / RTE
 * orders of magnitude) and kept coarse for the MVP. Every number lives here as
 * a {@see Coefficient} so it stays auditable (§13); nothing is inlined.
 *
 * Solar model: clear-sky "peak sun hours" follow a seasonal sinusoid (peaking at
 * the summer solstice); actual daily output = installed kWc × peak-sun-hours ×
 * cloud factor × performance ratio.
 */
final class EnergyCalibration
{
    /** Day of the year of maximum solar potential (summer solstice, ~21 June). */
    public function solarPeakDayOfYear(): Coefficient
    {
        return new Coefficient(172.0, 'day-of-year', 170.0, 174.0, 'Solstice d\'été', '2025-01-01');
    }

    /** Mean clear-sky daily peak-sun-hours over the year (France métropolitaine). */
    public function solarClearSkyPeakSunHoursMean(): Coefficient
    {
        return new Coefficient(
            value: 5.3,
            unit: 'h/day',
            min: 4.5,
            max: 6.0,
            source: 'PVGIS (irradiation journalière France, moyenne annuelle ciel clair)',
            reviewedOn: '2025-01-01',
        );
    }

    /** Half-amplitude of the seasonal swing of clear-sky peak-sun-hours. */
    public function solarSeasonalAmplitudeHours(): Coefficient
    {
        return new Coefficient(
            value: 2.8,
            unit: 'h/day',
            min: 2.0,
            max: 3.5,
            source: 'PVGIS (écart hiver/été de l\'irradiation journalière)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Fraction of production lost at full overcast: output under a fully clouded
     * sky ≈ (1 − thisValue) of the clear-sky output (diffuse light still gives
     * some yield).
     */
    public function solarCloudLossFactor(): Coefficient
    {
        return new Coefficient(
            value: 0.75,
            unit: 'fraction',
            min: 0.65,
            max: 0.85,
            source: 'Irradiance diffuse sous ciel couvert ≈ 15-35 % du ciel clair',
            reviewedOn: '2025-01-01',
        );
    }

    /** System performance ratio: inverter, wiring, temperature and soiling losses. */
    public function solarPerformanceRatio(): Coefficient
    {
        return new Coefficient(
            value: 0.80,
            unit: 'fraction',
            min: 0.75,
            max: 0.85,
            source: 'ADEME / PVGIS: performance ratio typique d\'une installation résidentielle',
            reviewedOn: '2025-01-01',
        );
    }

    /** Installed peak power of the single Phase 0-1 solar model. */
    public function defaultSolarPeakPowerKwc(): Coefficient
    {
        return new Coefficient(3.0, 'kWc', 3.0, 3.0, 'Installation résidentielle type (jeu, 1 seul modèle)', '2025-01-01');
    }

    /** Mean daily base household electricity demand (excluding fuel-oil heating). */
    public function householdDailyBaseDemandKwh(): Coefficient
    {
        return new Coefficient(
            value: 10.0,
            unit: 'kWh/day',
            min: 7.0,
            max: 13.0,
            source: 'ADEME/RTE: consommation électrique domestique hors chauffage (~3 000-4 000 kWh/an)',
            reviewedOn: '2025-01-01',
        );
    }

    /** Half-amplitude of the seasonal demand swing (higher in winter: lighting, usages). */
    public function householdDemandSeasonalAmplitudeKwh(): Coefficient
    {
        return new Coefficient(1.5, 'kWh/day', 1.0, 2.5, 'RTE: saisonnalité de la demande résidentielle', '2025-01-01');
    }

    /** Day of the year of peak demand (winter, aligned with the thermal minimum). */
    public function householdDemandPeakDayOfYear(): Coefficient
    {
        return new Coefficient(15.0, 'day-of-year', 5.0, 25.0, 'RTE: pointe hivernale de la demande résidentielle', '2025-01-01');
    }

    /**
     * Fraction of daily demand drawn during daylight hours (when solar is
     * producing). The rest falls in the evening/night peak — this split is what
     * makes a battery useful (bridging midday surplus to evening demand, §18).
     */
    public function daytimeDemandFraction(): Coefficient
    {
        return new Coefficient(
            value: 0.40,
            unit: 'fraction',
            min: 0.30,
            max: 0.50,
            source: 'RTE: courbe de charge résidentielle (pointe du soir)',
            reviewedOn: '2025-01-01',
        );
    }

    /** Usable capacity of the single Phase 0-1 battery model. */
    public function defaultBatteryCapacityKwh(): Coefficient
    {
        return new Coefficient(5.0, 'kWh', 5.0, 5.0, 'Batterie résidentielle type (jeu, 1 seule capacité)', '2025-01-01');
    }

    /** Battery round-trip efficiency (charge × discharge). */
    public function batteryRoundTripEfficiency(): Coefficient
    {
        return new Coefficient(
            value: 0.90,
            unit: 'fraction',
            min: 0.85,
            max: 0.95,
            source: 'Rendement aller-retour lithium-ion résidentiel (~90 %)',
            reviewedOn: '2025-01-01',
        );
    }

    /** One-way efficiency, so that charge × discharge = round-trip. */
    public function batteryOneWayEfficiency(): float
    {
        return sqrt($this->batteryRoundTripEfficiency()->value);
    }
}
