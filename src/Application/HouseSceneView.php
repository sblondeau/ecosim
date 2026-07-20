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
        // Envelope, one field per renovable surface (arbre travaux T1/T5/T6) —
        // per-surface so each can get its own visual, unlike the old single
        // coarse tier this replaces.
        public bool $roofInsulated,
        /** walls: none|interior|exterior. */
        public string $wallInsulation,
        /** glazing: single|double|triple. */
        public string $glazing,
        /** ventilation: none|double-flow. */
        public string $ventilation,
        public bool $thermalCurtains,
        /**
         * Draught-proofing done (window seals, door sweeps). Drives the
         * window's red band — but only while still single-glazed; new
         * double/triple-glazed frames are assumed to already seal (Tranche 7
         * window-coherence follow-up), even though the underlying gesture
         * mechanically still applies (it also covers doors).
         */
        public bool $draughtProofed,
        /**
         * Underfloor low-temperature emitters (arbre travaux). Reverses the
         * tranche 7 "hors coupe" exception: drawn as a discreet serpentine
         * under the living-room floor rather than skipped, since the slab
         * itself is already in frame there.
         */
        public bool $lowTempEmitters,
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
    ) {
    }
}
