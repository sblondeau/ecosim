<?php

declare(strict_types=1);

namespace App\Domain\Energy;

use App\Domain\Calibration\Coefficient;
use App\Domain\Weather\WeatherCalibration;

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
    public function __construct(
        // The winter demand peak shares the weather's thermal-minimum anchor;
        // deriving it from there keeps the two calendars from drifting apart.
        private WeatherCalibration $weather = new WeatherCalibration(),
    ) {
    }

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

    /**
     * Installed peak power of the plug-and-play solar kit (no installer, a
     * single balcony/garden panel with a micro-inverter) — the cheap entry
     * point below the full installation (arbre travaux, Tranche 5).
     */
    public function solarKitPeakPowerKwc(): Coefficient
    {
        return new Coefficient(
            value: 0.9,
            unit: 'kWc',
            min: 0.4,
            max: 1.5,
            source: 'Marché des kits solaires plug-and-play grand public (1-2 panneaux + micro-onduleur, ~300-600 Wc/panneau)',
            reviewedOn: '2026-07-17',
        );
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
        // Same mid-January anchor as the coldest day: derive it from the single
        // source of truth so the demand peak and the thermal minimum stay in sync.
        $coldest = $this->weather->coldestDayOfYear();

        return new Coefficient(
            value: $coldest->value,
            unit: 'day-of-year',
            min: $coldest->min,
            max: $coldest->max,
            source: 'RTE : pointe hivernale de la demande résidentielle, alignée sur le minimum thermique (WeatherCalibration::coldestDayOfYear)',
            reviewedOn: '2025-01-01',
        );
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
     * Primary-energy conversion factor of electricity (kWhPE per kWh final):
     * the regulatory French convention behind the DPE energy label. Fossil
     * fuels convert at 1.0 (their final energy IS primary), so only electricity
     * carries a factor — which is why an all-electric passoire scores badly on
     * the energy label even as it collapses on the climate one.
     */
    public function electricityPrimaryEnergyFactor(): Coefficient
    {
        return new Coefficient(
            value: 2.3,
            unit: 'kWhEP/kWh',
            min: 2.3,
            max: 2.3,
            source: 'Convention réglementaire française (arrêté DPE 3 avril 2021 ; Code de la construction)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * CO₂ content of domestic fuel oil, combustion + upstream — the DPE climate
     * label factor. Fuel oil is one of the most carbon-intensive heating energies.
     */
    public function fuelOilCo2GramsPerKwh(): Coefficient
    {
        return new Coefficient(
            value: 324.0,
            unit: 'gCO2e/kWh',
            min: 300.0,
            max: 340.0,
            source: 'Arrêté DPE 2021 / ADEME Base Carbone : facteur d\'émission du fioul domestique',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * CO₂ content of grid electricity used by the DPE climate label. France's
     * mostly-nuclear/renewable mix makes this low — the reason a heat pump
     * slashes emissions far more than it slashes primary energy or the bill.
     */
    public function electricityCo2GramsPerKwh(): Coefficient
    {
        return new Coefficient(
            value: 79.0,
            unit: 'gCO2e/kWh',
            min: 60.0,
            max: 80.0,
            source: 'Arrêté DPE 2021 : facteur d\'émission conventionnel de l\'électricité (ADEME Base Carbone ~60 g en conso)',
            reviewedOn: '2025-01-01',
        );
    }

    /**
     * SCOP of the air/water heat pump on high-temperature emitters (old
     * cast-iron radiators, ~65 °C water) — the scenario's default plumbing.
     * A heat pump is a system, not a box: sized for a boiler's water
     * temperature, it runs far from its efficient operating point.
     */
    public function heatPumpScopHighTempEmitters(): Coefficient
    {
        return new Coefficient(
            value: 2.5,
            unit: 'SCOP',
            min: 2.2,
            max: 2.8,
            source: 'ADEME / NF PAC : SCOP dégradé sur émetteurs haute température (~55-65 °C)',
            reviewedOn: '2026-07-17',
        );
    }

    /**
     * SCOP on low-temperature emitters (underfloor heating / oversized BT
     * radiators, ~35 °C water) — the nominal, sourced figure the heat pump
     * was designed around.
     */
    public function heatPumpScopLowTempEmitters(): Coefficient
    {
        return new Coefficient(
            value: 4.3,
            unit: 'SCOP',
            min: 4.0,
            max: 4.6,
            source: 'ADEME / NF PAC : SCOP nominal sur émetteurs basse température (~35 °C)',
            reviewedOn: '2026-07-17',
        );
    }

    /**
     * Seasonal efficiency of an automatic pellet (granulés) boiler — a
     * modern, well-regulated combustion appliance (arbre travaux T4).
     */
    public function pelletBoilerEfficiency(): Coefficient
    {
        return new Coefficient(value: 0.90, unit: 'fraction', min: 0.85, max: 0.95, source: 'ADEME : rendement chaudière automatique à granulés', reviewedOn: '2026-07-17');
    }

    /**
     * Energy content of wood pellets (net calorific value).
     */
    public function pelletEnergyKwhPerKg(): Coefficient
    {
        return new Coefficient(value: 4.6, unit: 'kWh/kg', min: 4.6, max: 5.2, source: 'Norme ENplus / ADEME : PCI granulés bois ~4,6-5 kWh/kg', reviewedOn: '2026-07-17');
    }

    /**
     * CO₂ content of wood pellets, combustion + upstream — the DPE climate
     * label factor. Biomass is near-carbon-neutral on combustion, so this is
     * far below fossil fuels.
     */
    public function pelletCo2GramsPerKwh(): Coefficient
    {
        return new Coefficient(value: 30.0, unit: 'gCO2e/kWh', min: 20.0, max: 40.0, source: 'ADEME Base Carbone : granulés bois (combustion + amont), ~30 g CO2e/kWh', reviewedOn: '2026-07-17');
    }

    /** DPE primary-energy factor for biomass (wood/pellets): 1.0, unlike electricity's 2.3. */
    public function pelletPrimaryEnergyFactor(): Coefficient
    {
        return new Coefficient(value: 1.0, unit: 'factor', min: 1.0, max: 1.0, source: 'Méthode DPE 2021 : coefficient d\'énergie primaire biomasse = 1,0', reviewedOn: '2026-07-17');
    }
}
