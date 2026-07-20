<?php

declare(strict_types=1);

namespace App\Domain\Finance\Work;

use App\Domain\Building\BuildingCalibration;
use App\Domain\Building\Household;
use App\Domain\Building\Ventilation;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\SceneSlot;

/**
 * Double-flow mechanical ventilation (VMC double flux): recovers heat from
 * extracted air instead of simply venting it. Pays off only once the
 * envelope holds heat worth recovering — see the caution in
 * {@see adviceFor()}, one of the game's three.
 */
final readonly class VentilationDoubleFlowWork implements RenovationDefinition
{
    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
        private BuildingCalibration $building = new BuildingCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'ventilation_double_flow';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Walls;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        if (Ventilation::None !== $household->envelope->ventilation) {
            return null;
        }

        return new RenovationOffer(
            title: Ventilation::DoubleFlow->label(),
            cost: Money::fromEuros($this->calibration->ventilationDoubleFlowCost()->value),
            resultingHousehold: $household->withEnvelope($household->envelope->withVentilation(Ventilation::DoubleFlow)),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        $poorlyInsulated = $this->building->envelopeLossFactor($household->envelope)
            > $this->building->poorlyInsulatedEnvelopeCeiling()->value;

        return $poorlyInsulated
            ? new RenovationAdvice(AdviceLevel::Caution, 'À poser plutôt APRÈS l\'isolation : la VMC double flux récupère la chaleur, autant qu\'il y en ait à récupérer.')
            : new RenovationAdvice(AdviceLevel::Info, 'Récupère la chaleur de l\'air extrait et renouvelle l\'air sainement.');
    }

    public function qualifiesForEnergyAid(): bool
    {
        return true;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return Ventilation::DoubleFlow === $household->envelope->ventilation ? $household->envelope->ventilation->label() : null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return Ventilation::DoubleFlow === $household->envelope->ventilation ? 'vmc-double-flow' : null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/icons/ventilation-double-flow.svg';
    }
}
