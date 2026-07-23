<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * How long each renovation chantier takes — the « délai » access-cost lever
 * (game-design §1: le levier est le coût d'accès = prix + délai + prérequis,
 * jamais une magnitude truquée).
 *
 * Orders of magnitude, in game-days, grouped by nature. The pose durations lean
 * on ADEME / trade guides (chaudière/PAC ~1-3 j, combles ~1 j, ITE ~1-3 sem.,
 * PV ~1-2 j). The LEAD times (carnet d'artisan RGE) and the éco-PTZ funds
 * release are honestly WITHOUT a hard source — assumed orders of magnitude
 * (§13), not maquillés en chiffres sourcés. To refine later; per-work here
 * rather than a per-slot tier because repair vs PAC (same slot) must differ.
 *
 * The asymmetry is the point: the emergency boiler repair is fast, everything
 * that replaces it is slow — so under the panne, repairing is rational on the
 * spot and installing the heat pump BEFORE the breakdown is the winning play.
 */
final readonly class RenovationDelays
{
    /** Éco-PTZ funds release (devis RGE → dossier banque → rétractation → déblocage). */
    private const int PTZ_FUNDS_RELEASE_DAYS = 42;

    public function for(string $workSlug): ChantierDelay
    {
        return match ($workSlug) {
            // The fast exception: an emergency dépannage, days not weeks.
            'boiler_repair' => new ChantierDelay(2, 1),
            // Cheap gestes and plug-and-play: no RGE carnet, quick.
            'draught_proofing', 'thermal_curtains' => new ChantierDelay(7, 1),
            'solar_kit' => new ChantierDelay(7, 1),
            'water_heater_thermo' => new ChantierDelay(14, 1),
            'home_battery' => new ChantierDelay(14, 1),
            // Envelope & ventilation: an RGE carnet, short-to-medium pose.
            'roof_insulation' => new ChantierDelay(21, 1),
            'ventilation_double_flow' => new ChantierDelay(21, 3),
            'glazing' => new ChantierDelay(28, 3),
            'wall_insulation_interior' => new ChantierDelay(28, 5),
            'low_temp_emitters' => new ChantierDelay(28, 5),
            // Generators & ITE: the long chain.
            'heat_pump', 'pellet_boiler' => new ChantierDelay(35, 2),
            'wall_insulation_exterior' => new ChantierDelay(35, 14),
            // Rooftop PV: déclaration mairie + Consuel/Enedis on top.
            'solar_panels' => new ChantierDelay(45, 2),
            default => new ChantierDelay(21, 3),
        };
    }

    /** @return int<0, max> */
    public function ptzFundsReleaseDays(): int
    {
        return self::PTZ_FUNDS_RELEASE_DAYS;
    }
}
