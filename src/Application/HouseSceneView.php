<?php

declare(strict_types=1);

namespace App\Application;

/**
 * The scene model of the local view (game-design §17): WHAT to show, in purely
 * semantic terms — equipment slots and their states, plus the sky facts. The
 * hygiene rule is absolute: no screen coordinates, colors or shapes here
 * ('broken', never « halo rouge ») — geometry belongs to the renderer template,
 * so the art direction can change (cutaway → isometric) without touching this.
 */
final readonly class HouseSceneView
{
    public function __construct(
        // Sky & ambience (all simulated facts — no decorative weather)
        /** Season key: winter|spring|summer|autumn. */
        public string $season,
        public int $cloudPct,
        /**
         * Sun height in its yearly cycle: 0 (winter solstice, lowest) to 1
         * (summer solstice, highest) — same seasonal sinusoid the solar
         * production model runs on, read here as a fact, not re-derived.
         */
        public float $sunElevationRatio,
        /** Freezing ambience today (roof/canopy snow dressing — instant). */
        public bool $frost,
        /**
         * Ground snow accumulation: 0-100, built from ~15 days of replayed
         * weather (+1 tier per consecutive freezing day, −1 per thaw day,
         * capped) — a gradual pile-up/melt, unlike the same-day {@see $frost}
         * used for the roof/canopy dusting.
         */
        public int $snowDepthPct,
        /** The panels produced today (glint). */
        public bool $producing,
        /** The boiler burnt fuel today (chimney smoke tracks combustion). */
        public bool $chimneySmoking,
        // Slots
        /** solar (roof PV): empty|kit|full — no panels, a small kit, or the full install. */
        public string $solarState,
        public string $roofLabel,
        public string $insulationLabel,
        /** heating: fioul|fioul-broken|heat-pump|pellet. */
        public string $heatingState,
        public string $heatingLabel,
        /** garage: empty|installed. */
        public string $garageState,
        public string $garageLabel,
        /**
         * The tank was upgraded to a thermodynamic one. A bool, not a state:
         * the plain electric tank is the starting equipment, so it gets no
         * visual — drawing it would claim the player did something.
         */
        public bool $waterHeaterThermo,
        /** Indoor feel bucket: cold|cool|warm — drives the living-room tint. */
        public string $comfortState,
        /**
         * The active envelope CSS layers for the cutaway (game-design §17):
         * the house--* gates HouseShell emits, sourced from the catalogue's
         * sceneLayerFor(). Equipment visuals are NOT here — they are selected
         * from the equipment states above (heatingState, solarState…).
         *
         * @var list<string>
         */
        public array $envelopeLayers,
    ) {
    }
}
