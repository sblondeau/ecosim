<?php

declare(strict_types=1);

namespace App\Domain\Finance\Work;

use App\Domain\Building\BuildingCalibration;
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
 * Air-to-water heat pump: the way out of fuel oil, and the work whose payoff
 * depends most on what was done before it (an uninsulated house forces an
 * oversized machine that never pays back).
 */
final readonly class HeatPumpWork implements RenovationDefinition
{
    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
        private BuildingCalibration $building = new BuildingCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'heat_pump';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Heating;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        if (HeatingSystem::HeatPump === $household->heatingSystem) {
            return null;
        }

        return new RenovationOffer(
            title: 'Pompe à chaleur air/eau',
            cost: Money::fromEuros($this->calibration->heatPumpInstallCost()->value),
            resultingHousehold: $household->withHeatingSystem(HeatingSystem::HeatPump),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        $poorlyInsulated = $this->building->envelopeLossFactor($household->envelope)
            > $this->building->poorlyInsulatedEnvelopeCeiling()->value;

        return match (true) {
            $household->boilerBroken => new RenovationAdvice(AdviceLevel::Info, 'L\'occasion de sortir du fioul. Vérifiez que la maison est un minimum isolée, sinon la PAC sera bridée.'),
            $poorlyInsulated => new RenovationAdvice(AdviceLevel::Caution, 'Maison peu isolée → PAC surdimensionnée, factures qui resteront hautes. Isolez d\'abord.'),
            default => new RenovationAdvice(AdviceLevel::Info, 'Bon rendement attendu : la maison est suffisamment isolée pour une PAC efficace.'),
        };
    }

    public function qualifiesForEnergyAid(): bool
    {
        return true;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return HeatingSystem::HeatPump === $household->heatingSystem ? HeatingSystem::HeatPump->label() : null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        // Equipment: drawn as a <twig:scene:*> component selected from the
        // household's equipment state, not via an envelope house--* gate.
        return null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/heat-pump.svg';
    }
}
