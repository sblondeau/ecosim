<?php

declare(strict_types=1);

namespace App\Domain\Energy;

use App\Domain\Calibration\Coefficient;

use function sqrt;

/**
 * Sourced coefficients for the Phase 0-1 energy loop: solar production, base
 * household electricity demand, battery storage and heating-energy conversion
 * (game-design §3, §8, §12, §15).
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
    /**
     * Day of the year of maximum clear-sky solar potential. Purely geometric
     * (no thermal lag, unlike temperature): the clear-sky component peaks at
     * the summer solstice; cloud effects are modelled separately.
     */
    public function solarPeakDayOfYear(): Coefficient
    {
        return new Coefficient(
            value: 172.0,
            unit: 'day-of-year',
            min: 170.0,
            max: 174.0,
            source: 'Fait astronomique — solstice d\'été le 20-21 juin (éphémérides IMCCE, Observatoire de Paris)',
            reviewedOn: '2025-01-01',
        );
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

    /** Half-width of the day-to-day demand noise (laundry days, guests, absences…). */
    public function householdDemandDailyNoiseKwh(): Coefficient
    {
        return new Coefficient(
            value: 1.5,
            unit: 'kWh/day',
            min: 1.0,
            max: 2.5,
            source: 'Calibration de jeu : variabilité journalière des usages domestiques (~±15 % de la base)',
            reviewedOn: '2025-01-01',
        );
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

    /**
     * Daily output ceiling of the household's portable electric heaters
     * (already owned — every household has some; they plug them in the day
     * the boiler dies). Direct Joule heating: 1 kWh electricity = 1 kWh heat,
     * the worst-performing heating there is (game-design §12, the anti-PAC).
     */
    public function emergencyHeatersMaxKwhPerDay(): Coefficient
    {
        return new Coefficient(
            value: 96.0,
            unit: 'kWh/day',
            min: 48.0,
            max: 144.0,
            source: 'Ordre de grandeur : 2 convecteurs mobiles de ~2 kW en fonctionnement continu (parc d\'appoint domestique typique, ADEME)',
            reviewedOn: '2025-01-01',
        );
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
}
