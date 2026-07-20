<?php

declare(strict_types=1);

namespace App\Domain\Finance\Work;

use App\Domain\Building\Household;
use App\Domain\Building\WaterHeater;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\SceneSlot;

/**
 * Swap the electric-tank water heater for a thermodynamic one: hot water is
 * ~15 % of household energy, easy to forget next to space heating, and a
 * small heat pump divides its consumption by roughly 3.
 */
final readonly class WaterHeaterThermoWork implements RenovationDefinition
{
    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'water_heater_thermo';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Heating;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        if (WaterHeater::Thermodynamic === $household->waterHeater) {
            return null;
        }

        return new RenovationOffer(
            title: 'Chauffe-eau thermodynamique',
            cost: Money::fromEuros($this->calibration->waterHeaterThermoCost()->value),
            resultingHousehold: $household->withWaterHeater(WaterHeater::Thermodynamic),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(
            AdviceLevel::Info,
            'L\'eau chaude = ~15 % de l\'énergie, souvent oubliée : le thermodynamique divise sa conso par ~3.',
        );
    }

    public function isEnergyPerformanceWork(): bool
    {
        return true;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return WaterHeater::Thermodynamic === $household->waterHeater ? $household->waterHeater->label() : null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return WaterHeater::Thermodynamic === $household->waterHeater ? 'water-heater-thermo' : null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/water-heater-thermo.svg';
    }
}
