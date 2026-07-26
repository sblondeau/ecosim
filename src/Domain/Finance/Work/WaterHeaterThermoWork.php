<?php

declare(strict_types=1);

namespace App\Domain\Finance\Work;

use App\Domain\Building\Household;
use App\Domain\Building\WaterHeater;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\ChantierDelay;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\SceneSlot;
use App\Domain\Finance\WorkPedagogy;

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

    public function qualifiesForEnergyAid(): bool
    {
        return true;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return WaterHeater::Thermodynamic === $household->waterHeater ? $household->waterHeater->label() : null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        // Equipment: drawn as a <twig:scene:*> component selected from the
        // household's equipment state, not via an envelope house--* gate.
        return null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/water-heater-thermo.svg';
    }

    public function delay(): ChantierDelay
    {
        return new ChantierDelay(14, 1);
    }

    public function requiresProfessional(): bool
    {
        return true;
    }

    public function pedagogy(): WorkPedagogy
    {
        return new WorkPedagogy(
            'Un chauffe-eau thermodynamique est une petite pompe à chaleur dédiée à l\'eau chaude sanitaire : environ 3 fois moins d\'électricité qu\'un ballon électrique classique.',
            'L\'eau chaude pèse lourd dans la facture d\'une maison bien isolée. Il a besoin d\'un volume d\'air à prélever (buanderie, garage) pour bien fonctionner.',
            ['ADEME — eau chaude sanitaire'],
        );
    }
}
