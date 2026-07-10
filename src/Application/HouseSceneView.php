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
        /** Freezing ambience (ground/roof snow dressing). */
        public bool $frost,
        /** The panels produced today (glint). */
        public bool $producing,
        /** The boiler burnt fuel today (chimney smoke tracks combustion). */
        public bool $chimneySmoking,
        // Slots
        /** roof: empty|installed. */
        public string $roofState,
        public string $roofLabel,
        /** walls/attic insulation: 0 (original), 1 (retrofitted), 2 (reinforced). */
        public int $insulationTier,
        public string $insulationLabel,
        /** heating: fioul|fioul-broken|heat-pump. */
        public string $heatingState,
        public string $heatingLabel,
        /** garage: empty|installed. */
        public string $garageState,
        public string $garageLabel,
        /** Indoor feel bucket: cold|cool|warm — drives the living-room tint. */
        public string $comfortState,
    ) {
    }
}
