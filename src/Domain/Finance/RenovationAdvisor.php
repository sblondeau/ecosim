<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\BuildingCalibration;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;

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
    ) {
    }

    /** Every work carries a word of advice — the match below is exhaustive. */
    public function adviceFor(Renovation $work, Household $household): RenovationAdvice
    {
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
            Renovation::HeatPump => match (true) {
                $household->boilerBroken => new RenovationAdvice(AdviceLevel::Info, 'L\'occasion de sortir du fioul. Vérifiez que la maison est un minimum isolée, sinon la PAC sera bridée.'),
                $poorlyInsulated => new RenovationAdvice(AdviceLevel::Caution, 'Maison peu isolée → PAC surdimensionnée, factures qui resteront hautes. Isolez d\'abord.'),
                default => new RenovationAdvice(AdviceLevel::Info, 'Bon rendement attendu : la maison est suffisamment isolée pour une PAC efficace.'),
            },
            Renovation::BoilerRepair => new RenovationAdvice(
                AdviceLevel::Info,
                'Rapide et peu cher, mais vous restez au fioul (facture et CO₂ élevés).',
            ),
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
            Renovation::LowTempEmitters => HeatingSystem::HeatPump === $household->heatingSystem
                ? new RenovationAdvice(AdviceLevel::Info, 'Fait passer le SCOP de votre PAC de ~2,5 à ~4,3 : moins d\'électricité pour la même chaleur.')
                : new RenovationAdvice(AdviceLevel::Info, 'Utile surtout avec une pompe à chaleur (améliore fortement son rendement) ; sans effet sur une chaudière.'),
            Renovation::PelletBoiler => new RenovationAdvice(
                AdviceLevel::Info,
                'Combustible bon marché et bas carbone (~30 g/kWh), mais manuel : stockage et chargement du silo.',
            ),
            Renovation::VentilationDoubleFlow => $poorlyInsulated
                ? new RenovationAdvice(AdviceLevel::Caution, 'À poser plutôt APRÈS l\'isolation : la VMC double flux récupère la chaleur, autant qu\'il y en ait à récupérer.')
                : new RenovationAdvice(AdviceLevel::Info, 'Récupère la chaleur de l\'air extrait et renouvelle l\'air sainement.'),
            Renovation::WaterHeaterThermo => new RenovationAdvice(
                AdviceLevel::Info,
                'L\'eau chaude = ~15 % de l\'énergie, souvent oubliée : le thermodynamique divise sa conso par ~3.',
            ),
            Renovation::DraughtProofing => new RenovationAdvice(
                AdviceLevel::Info,
                'Geste bon marché : coupe les courants d\'air (quelques % de pertes). Utile en complément — pas un gros levier.',
            ),
            Renovation::ThermalCurtains => new RenovationAdvice(
                AdviceLevel::Info,
                'Geste bon marché : un peu de confort près des fenêtres la nuit. Petit levier, pas un substitut à l\'isolation.',
            ),
        };
    }
}
