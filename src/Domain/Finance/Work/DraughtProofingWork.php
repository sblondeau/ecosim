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
 * Draught-proofing: window seals, door sweeps, mastic. A cheap daily gesture
 * with a small effect (arbre travaux, Tranche 6) — window seals are invisible
 * at this scale, so unlike every other work this one has no scene layer.
 */
final readonly class DraughtProofingWork implements RenovationDefinition
{
    private const string LABEL = 'Calfeutrage / joints';

    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'draught_proofing';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Living;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        if ($household->envelope->draughtProofed) {
            return null;
        }

        return new RenovationOffer(
            title: self::LABEL,
            cost: Money::fromEuros($this->calibration->draughtProofingCost()->value),
            resultingHousehold: $household->withEnvelope($household->envelope->withDraughtProofed(true)),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(
            AdviceLevel::Info,
            'Geste bon marché : coupe les courants d\'air (quelques % de pertes). Utile en complément — pas un gros levier.',
        );
    }

    public function isEnergyPerformanceWork(): bool
    {
        return false;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return $household->envelope->draughtProofed ? self::LABEL : null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        // Deliberate exception (arbre travaux, Tranche 7): window seals are
        // invisible at this scale, so this work never activates a layer.
        return null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/icons/draught-proofing.svg';
    }
}
