<?php

declare(strict_types=1);

namespace App\Domain\Finance\Work;

use App\Domain\Building\Household;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\SceneSlot;

/**
 * Thermal curtains (lined), a handful of windows. A cheap daily gesture with
 * a small effect (arbre travaux, Tranche 6).
 */
final readonly class ThermalCurtainsWork implements RenovationDefinition
{
    private const string LABEL = 'Rideaux thermiques';

    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'thermal_curtains';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Living;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        if ($household->envelope->thermalCurtains) {
            return null;
        }

        return new RenovationOffer(
            title: self::LABEL,
            cost: Money::fromEuros($this->calibration->thermalCurtainsCost()->value),
            resultingHousehold: $household->withEnvelope($household->envelope->withThermalCurtains(true)),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(
            AdviceLevel::Info,
            'Geste bon marché : un peu de confort près des fenêtres la nuit. Petit levier, pas un substitut à l\'isolation.',
        );
    }

    public function isEnergyPerformanceWork(): bool
    {
        return false;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return $household->envelope->thermalCurtains ? self::LABEL : null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return $household->envelope->thermalCurtains ? 'curtains' : null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/icons/thermal-curtains.svg';
    }
}
