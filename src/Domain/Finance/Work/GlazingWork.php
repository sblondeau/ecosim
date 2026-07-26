<?php

declare(strict_types=1);

namespace App\Domain\Finance\Work;

use App\Domain\Building\BuildingCalibration;
use App\Domain\Building\Glazing;
use App\Domain\Building\Household;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\ChantierDelay;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\SceneSlot;
use App\Domain\Finance\WorkPedagogy;

use function sprintf;

/**
 * Windows: single → double → triple glazing. The only tiered work — its
 * offer targets the next tier up and disappears once triple is reached — and
 * the only one where {@see doneLabelFor()} and {@see offerFor()} both answer
 * non-null at once: double glazing is done AND still upgradeable to triple.
 */
final readonly class GlazingWork implements RenovationDefinition
{
    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
        private BuildingCalibration $building = new BuildingCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'glazing';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Walls;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        $target = match ($household->envelope->glazing) {
            Glazing::Single => Glazing::Double,
            Glazing::Double => Glazing::Triple,
            Glazing::Triple => null,
        };
        if (null === $target) {
            return null;
        }

        return new RenovationOffer(
            title: sprintf('Menuiseries — %s', $target->label()),
            cost: Money::fromEuros($this->calibration->glazingUpgradeCost()->value),
            resultingHousehold: $household->withEnvelope($household->envelope->withGlazing($target)),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        $poorlyInsulated = $this->building->envelopeLossFactor($household->envelope)
            > $this->building->poorlyInsulatedEnvelopeCeiling()->value;

        return $poorlyInsulated
            ? new RenovationAdvice(AdviceLevel::Caution, 'Le vitrage pèse peu (~10 % des pertes) : priorisez d\'abord combles et murs.')
            : new RenovationAdvice(AdviceLevel::Info, 'Complète l\'isolation ; gagne surtout du confort (paroi froide) et de l\'acoustique. Le triple n\'est utile qu\'en climat froid.');
    }

    public function qualifiesForEnergyAid(): bool
    {
        return true;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return Glazing::Single === $household->envelope->glazing ? null : $household->envelope->glazing->label();
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return match ($household->envelope->glazing) {
            Glazing::Single => null,
            Glazing::Double => 'glazing-double',
            Glazing::Triple => 'glazing-triple',
        };
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/icons/glazing.svg';
    }

    public function delay(): ChantierDelay
    {
        return new ChantierDelay(28, 3);
    }

    public function requiresProfessional(): bool
    {
        return true;
    }

    public function pedagogy(): WorkPedagogy
    {
        return new WorkPedagogy(
            'Remplacer les menuiseries par du double ou triple vitrage réduit les pertes par les fenêtres et supprime la sensation de paroi froide à côté des vitres.',
            'Gain énergétique modéré (les fenêtres sont une petite surface), mais fort sur le confort et l\'acoustique. Le triple vitrage n\'a de sens qu\'une fois les murs et le toit bien isolés.',
            ['ADEME — fenêtres et menuiseries'],
        );
    }
}
