<?php

declare(strict_types=1);

namespace App\Domain\Finance\Work;

use App\Domain\Building\Household;
use App\Domain\Energy\EnergyCalibration;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\SceneSlot;

use function sprintf;

/**
 * Rooftop solar installation (the single 3 kWc catalogue model). The gate is
 * the full install's own power, not zero: this also offers it as the upgrade
 * path from the plug-and-play kit (arbre travaux, Tranche 5).
 */
final readonly class SolarPanelsWork implements RenovationDefinition
{
    private const string TITLE_FORMAT = 'Panneaux solaires %.0f kWc';

    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
        private EnergyCalibration $energy = new EnergyCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'solar_panels';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Roof;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        if ($household->solarKwc >= $this->energy->defaultSolarPeakPowerKwc()->value) {
            return null;
        }

        $kwc = $this->energy->defaultSolarPeakPowerKwc()->value;

        return new RenovationOffer(
            title: sprintf(self::TITLE_FORMAT, $kwc),
            cost: Money::fromEuros($this->calibration->solarInstallCost()->value),
            resultingHousehold: $household->withSolarKwc($kwc),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(
            AdviceLevel::Info,
            'Réduit la facture d\'électricité. Plus rentable une fois les besoins de chauffage réduits.',
        );
    }

    public function isEnergyPerformanceWork(): bool
    {
        return false;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return $household->solarKwc >= $this->energy->defaultSolarPeakPowerKwc()->value
            ? sprintf(self::TITLE_FORMAT, $household->solarKwc)
            : null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return $household->solarKwc >= $this->energy->defaultSolarPeakPowerKwc()->value ? 'solar-full' : null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/solar-panels.svg';
    }
}
