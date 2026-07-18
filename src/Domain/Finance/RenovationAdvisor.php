<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\BuildingCalibration;
use App\Domain\Building\Household;
use LogicException;

use function sprintf;

/**
 * Non-prescriptive advice about renovations (game-design: pédagogie par les
 * systèmes, pas de dirigisme). For an available work and the current house,
 * returns an informative repère (Info) or a caution against a genuine
 * sequencing mistake (Caution) — never a "do this next". A caution is reserved
 * for the few real ordering mistakes: a heat pump in a poorly-insulated house,
 * glazing prioritised before the envelope is treated, and double-flow
 * ventilation installed before the envelope is insulated. Pure and deterministic.
 */
final readonly class RenovationAdvisor
{
    public function __construct(
        private BuildingCalibration $building = new BuildingCalibration(),
        private RenovationCatalog $catalog = new RenovationCatalog(),
    ) {
    }

    /**
     * Every work carries a word of advice: the catalogue answers migrated
     * works, the legacy match answers the rest (exhaustive together).
     */
    public function adviceFor(Renovation $work, Household $household): RenovationAdvice
    {
        $definition = $this->catalog->tryGet($work->value);
        if (null !== $definition) {
            return $definition->adviceFor($household);
        }

        $poorlyInsulated = $this->building->envelopeLossFactor($household->envelope)
            > $this->building->poorlyInsulatedEnvelopeCeiling()->value;

        return match ($work) {
            Renovation::RoofInsulation => new RenovationAdvice(
                AdviceLevel::Info,
                'Souvent le meilleur rapport gain/prix : ~24 % des pertes, et peu cher.',
            ),
            Renovation::WallInsulationInterior => new RenovationAdvice(
                AdviceLevel::Info,
                'ITI : moins chère, mais grignote la surface habitable et laisse des ponts thermiques.',
            ),
            Renovation::WallInsulationExterior => new RenovationAdvice(
                AdviceLevel::Info,
                'ITE : plus chère, mais meilleure (pas de pont thermique) et ravale la façade.',
            ),
            Renovation::Glazing => $poorlyInsulated
                ? new RenovationAdvice(AdviceLevel::Caution, 'Le vitrage pèse peu (~10 % des pertes) : priorisez d\'abord combles et murs.')
                : new RenovationAdvice(AdviceLevel::Info, 'Complète l\'isolation ; gagne surtout du confort (paroi froide) et de l\'acoustique. Le triple n\'est utile qu\'en climat froid.'),
            Renovation::SolarKit => new RenovationAdvice(
                AdviceLevel::Info,
                'Le premier pas accessible : sans installateur ni aide, rendement modeste.',
            ),
            Renovation::SolarPanels => new RenovationAdvice(
                AdviceLevel::Info,
                'Réduit la facture d\'électricité. Plus rentable une fois les besoins de chauffage réduits.',
            ),
            Renovation::HomeBattery => new RenovationAdvice(
                AdviceLevel::Info,
                'Stocke le surplus solaire pour le consommer le soir.',
            ),
            Renovation::VentilationDoubleFlow => $poorlyInsulated
                ? new RenovationAdvice(AdviceLevel::Caution, 'À poser plutôt APRÈS l\'isolation : la VMC double flux récupère la chaleur, autant qu\'il y en ait à récupérer.')
                : new RenovationAdvice(AdviceLevel::Info, 'Récupère la chaleur de l\'air extrait et renouvelle l\'air sainement.'),
            Renovation::DraughtProofing => new RenovationAdvice(
                AdviceLevel::Info,
                'Geste bon marché : coupe les courants d\'air (quelques % de pertes). Utile en complément — pas un gros levier.',
            ),
            Renovation::ThermalCurtains => new RenovationAdvice(
                AdviceLevel::Info,
                'Geste bon marché : un peu de confort près des fenêtres la nuit. Petit levier, pas un substitut à l\'isolation.',
            ),
            // Migrated to the catalogue (tasks 3-5): a definition always
            // answers these before the match is reached. Reaching here would
            // mean defaultWorks() lost an entry — a real bug, not a legal state.
            default => throw new LogicException(sprintf('"%s" is migrated to the renovation catalogue — the bridge above should have answered it.', $work->value)),
        };
    }
}
