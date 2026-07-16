<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\BuildingCalibration;
use App\Domain\Building\Household;

/**
 * Non-prescriptive advice about renovations (game-design: pédagogie par les
 * systèmes, pas de dirigisme). For an available work and the current house,
 * returns an informative repère (Info) or a caution against a genuine
 * sequencing mistake (Caution) — never a "do this next". Only two situations
 * warrant a caution: a heat pump in a poorly-insulated house, and glazing
 * prioritised before the envelope is treated. Pure and deterministic.
 */
final readonly class RenovationAdvisor
{
    public function __construct(
        private BuildingCalibration $building = new BuildingCalibration(),
    ) {
    }

    public function adviceFor(Renovation $work, Household $household): ?RenovationAdvice
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
            Renovation::SolarPanels => new RenovationAdvice(
                AdviceLevel::Info,
                'Réduit la facture d\'électricité. Plus rentable une fois les besoins de chauffage réduits.',
            ),
            Renovation::HomeBattery => new RenovationAdvice(
                AdviceLevel::Info,
                'Stocke le surplus solaire pour le consommer le soir.',
            ),
        };
    }
}
