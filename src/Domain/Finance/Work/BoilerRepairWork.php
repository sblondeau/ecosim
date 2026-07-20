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
 * Fix the broken fuel-oil boiler: the cheap way back to normal after the
 * breakdown event, and the alternative to the heat pump. Restores the
 * starting state rather than adding anything — no done chip, no scene layer.
 */
final readonly class BoilerRepairWork implements RenovationDefinition
{
    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'boiler_repair';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Heating;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        if (!$household->boilerBroken) {
            return null;
        }

        return new RenovationOffer(
            title: 'Réparer la chaudière fioul',
            cost: Money::fromEuros($this->calibration->boilerRepairCost()->value),
            resultingHousehold: $household->withBoilerBroken(false),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(
            AdviceLevel::Info,
            'Rapide et peu cher, mais vous restez au fioul (facture et CO₂ élevés).',
        );
    }

    public function isEnergyPerformanceWork(): bool
    {
        return false;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/boiler-fioul.svg';
    }
}
