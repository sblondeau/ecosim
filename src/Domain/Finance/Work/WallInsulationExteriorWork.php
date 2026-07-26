<?php

declare(strict_types=1);

namespace App\Domain\Finance\Work;

use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
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
 * Exterior wall insulation (ITE): the dearer of the two wall-insulation
 * variants, no thermal bridge and keeps living space, and re-clads the
 * façade. Mutually exclusive with the interior variant (ITI) — see
 * {@see offerFor()}.
 */
final readonly class WallInsulationExteriorWork implements RenovationDefinition
{
    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'wall_insulation_exterior';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Walls;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        // ITI et ITE mutuellement exclusifs : dès que les murs sont isolés, plus d'offre murs.
        if (WallInsulation::None !== $household->envelope->walls) {
            return null;
        }

        return new RenovationOffer(
            title: 'Isolation des murs — extérieure (ITE)',
            cost: Money::fromEuros($this->calibration->wallInsulationExteriorCost()->value),
            resultingHousehold: $household->withEnvelope($household->envelope->withWalls(WallInsulation::Exterior)),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(
            AdviceLevel::Info,
            'ITE : plus chère, mais meilleure (pas de pont thermique) et ravale la façade.',
        );
    }

    public function qualifiesForEnergyAid(): bool
    {
        return true;
    }

    public function doneLabelFor(Household $household): ?string
    {
        // The drawer's chip prefixes the surface ("Murs — ") to the enum
        // label; the offer title above does not share this text.
        return WallInsulation::Exterior === $household->envelope->walls
            ? 'Murs — '.$household->envelope->walls->label()
            : null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return WallInsulation::Exterior === $household->envelope->walls ? 'walls-exterior' : null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/icons/insulation.svg';
    }

    public function delay(): ChantierDelay
    {
        return new ChantierDelay(35, 14);
    }

    public function requiresProfessional(): bool
    {
        return true;
    }

    public function pedagogy(): WorkPedagogy
    {
        return new WorkPedagogy(
            'L\'isolation des murs par l\'extérieur (ITE) enveloppe la maison d\'un isolant sous un nouveau ravalement. Elle traite les ponts thermiques et ne réduit pas la surface intérieure.',
            'La solution la plus performante sur les murs, mais la plus chère (ravalement inclus) et la plus longue. Idéale si une façade est de toute façon à refaire.',
            ['ADEME — isolation des murs par l\'extérieur'],
        );
    }
}
