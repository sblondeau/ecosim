<?php

declare(strict_types=1);

namespace App\Domain\Finance\Work;

use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\SceneSlot;

/**
 * Automatic wood-pellet boiler: replaces the generator, cheap and low-carbon
 * fuel, but manual — stock and silo loading, unlike the heat pump's set-and-
 * forget electrification.
 */
final readonly class PelletBoilerWork implements RenovationDefinition
{
    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'pellet_boiler';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Heating;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        if (HeatingSystem::PelletBoiler === $household->heatingSystem) {
            return null;
        }

        return new RenovationOffer(
            title: HeatingSystem::PelletBoiler->label(),
            cost: Money::fromEuros($this->calibration->pelletBoilerCost()->value),
            resultingHousehold: $household->withHeatingSystem(HeatingSystem::PelletBoiler),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(
            AdviceLevel::Info,
            'Combustible bon marché et bas carbone (~30 g/kWh), mais manuel : stockage et chargement du silo.',
        );
    }

    public function qualifiesForEnergyAid(): bool
    {
        return true;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return HeatingSystem::PelletBoiler === $household->heatingSystem ? HeatingSystem::PelletBoiler->label() : null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return HeatingSystem::PelletBoiler === $household->heatingSystem ? 'heating-pellet' : null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/boiler-pellet.svg';
    }
}
