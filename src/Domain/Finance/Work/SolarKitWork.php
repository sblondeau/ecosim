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
 * The plug-and-play kit — no installer, no aid — is the cheap entry point:
 * available on a bare roof only, superseded by the full install
 * ({@see SolarPanelsWork}).
 */
final readonly class SolarKitWork implements RenovationDefinition
{
    private const string TITLE_FORMAT = 'Kit solaire plug-and-play %.1f kWc';

    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
        private EnergyCalibration $energy = new EnergyCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'solar_kit';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Garage;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        if (0.0 !== $household->solarKwc) {
            return null;
        }

        $kwc = $this->energy->solarKitPeakPowerKwc()->value;

        return new RenovationOffer(
            title: sprintf(self::TITLE_FORMAT, $kwc),
            cost: Money::fromEuros($this->calibration->solarKitInstallCost()->value),
            resultingHousehold: $household->withSolarKwc($kwc),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(
            AdviceLevel::Info,
            'Le premier pas accessible : sans installateur ni aide, rendement modeste.',
        );
    }

    public function isEnergyPerformanceWork(): bool
    {
        return false;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return $household->solarKwc === $this->energy->solarKitPeakPowerKwc()->value
            ? sprintf(self::TITLE_FORMAT, $household->solarKwc)
            : null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return $household->solarKwc === $this->energy->solarKitPeakPowerKwc()->value ? 'solar-kit' : null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/solar-kit.svg';
    }
}
