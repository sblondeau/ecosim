<?php

declare(strict_types=1);

namespace App\Domain\Weather;

use App\Domain\Calibration\Coefficient;

/**
 * Sourced coefficients driving the Phase 0-1 weather model (nébulosité +
 * température, game-design §5 layer 2 + §15).
 *
 * Values are calibrated to metropolitan France (Météo-France 1991-2020 climate
 * normals) and kept deliberately coarse for the MVP — the point is a plausible
 * order of magnitude with a documented range, not a precise local climatology.
 * Every number lives here as a {@see Coefficient} so it stays auditable (§13);
 * no weather magic number is inlined in the generator.
 */
final class WeatherCalibration
{
    /**
     * Annual mean of the daily-mean air temperature, France métropolitaine.
     */
    public function annualMeanTemperatureC(): Coefficient
    {
        return new Coefficient(
            value: 12.5,
            unit: '°C',
            min: 11.0,
            max: 13.5,
            source: 'Météo-France, normales climatiques 1991-2020 (France métropolitaine)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Half-amplitude of the seasonal swing of the daily-mean temperature
     * (peak summer mean ≈ annualMean + amplitude, deep winter ≈ annualMean − amplitude).
     */
    public function seasonalTemperatureAmplitudeC(): Coefficient
    {
        return new Coefficient(
            value: 7.5,
            unit: '°C',
            min: 6.0,
            max: 9.0,
            source: 'Météo-France, normales 1991-2020 (écart hiver/été des moyennes mensuelles)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Day of the year of the coldest daily mean. The thermal minimum lags the
     * winter solstice by ~3-4 weeks, landing in mid-January.
     */
    public function coldestDayOfYear(): Coefficient
    {
        return new Coefficient(
            value: 15.0,
            unit: 'day-of-year',
            min: 5.0,
            max: 25.0,
            source: 'Climatologie: décalage saisonnier du minimum thermique (~mi-janvier)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Amplitude of the day-to-day temperature noise around the seasonal mean
     * (weather variability), applied as a uniform ± band per day.
     */
    public function dailyTemperatureNoiseC(): Coefficient
    {
        return new Coefficient(
            value: 3.0,
            unit: '°C',
            min: 2.0,
            max: 4.0,
            source: 'Ordre de grandeur de l\'écart-type des anomalies journalières (Météo-France)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Annual mean cloud-cover fraction (0 = clear sky, 1 = fully overcast).
     */
    public function annualMeanCloudCover(): Coefficient
    {
        return new Coefficient(
            value: 0.50,
            unit: 'fraction',
            min: 0.40,
            max: 0.60,
            source: 'Ordre de grandeur de la nébulosité moyenne, France métropolitaine',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Half-amplitude of the seasonal cloud-cover swing (cloudier in winter,
     * clearer in summer — mirrors the sunshine-hours seasonality in France).
     */
    public function seasonalCloudAmplitude(): Coefficient
    {
        return new Coefficient(
            value: 0.12,
            unit: 'fraction',
            min: 0.08,
            max: 0.18,
            source: 'Météo-France, ensoleillement mensuel (été plus ensoleillé que l\'hiver)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Day-to-day spread of the cloud cover around its seasonal mean.
     */
    public function dailyCloudSpread(): Coefficient
    {
        return new Coefficient(
            value: 0.70,
            unit: 'fraction',
            min: 0.50,
            max: 0.90,
            source: 'Calibration de jeu: variabilité journalière de la nébulosité',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * Number of days between cloud-cover control points. Larger = more
     * persistent cloudy/clear spells (multi-day weather patterns).
     */
    public function cloudPersistenceDays(): Coefficient
    {
        return new Coefficient(
            value: 4.0,
            unit: 'day',
            min: 2.0,
            max: 7.0,
            source: 'Calibration de jeu: durée typique d\'un régime météo (quelques jours)',
            reviewedOn: '2025-01-01',
        );
    }
}
