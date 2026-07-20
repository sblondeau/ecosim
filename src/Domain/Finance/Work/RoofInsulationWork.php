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
 * Insulate the attic/roof: priority #1 of the envelope drawer, ~24 % of a
 * passoire's losses for a comparatively cheap ticket.
 */
final readonly class RoofInsulationWork implements RenovationDefinition
{
    private const string LABEL = 'Isolation des combles';

    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'roof_insulation';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Walls;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        if ($household->envelope->roofInsulated) {
            return null;
        }

        return new RenovationOffer(
            title: self::LABEL,
            cost: Money::fromEuros($this->calibration->roofInsulationCost()->value),
            resultingHousehold: $household->withEnvelope($household->envelope->withRoofInsulated(true)),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(
            AdviceLevel::Info,
            'Souvent le meilleur rapport gain/prix : ~24 % des pertes, et peu cher.',
        );
    }

    public function qualifiesForEnergyAid(): bool
    {
        return true;
    }

    public function doneLabelFor(Household $household): ?string
    {
        // Deliberately NOT self::LABEL: the drawer's "done" chip reads
        // "Combles isolés" (a state), not the offer's CTA title.
        return $household->envelope->roofInsulated ? 'Combles isolés' : null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return $household->envelope->roofInsulated ? 'roof-ins' : null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/icons/insulation.svg';
    }
}
