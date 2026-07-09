<?php

declare(strict_types=1);

namespace App\Application;

/**
 * A ready-to-render no-JS SVG sparkline of the recent weather.
 *
 * Nothing is stored for this: the weather is seeded and deterministic, so the
 * last days are simply recomputed from the generator ({@see GameViewFactory}).
 * Points are pre-projected into the fixed SVG viewBox so the template stays
 * free of math.
 */
final readonly class SparklineView
{
    /** ViewBox width the points are projected into. */
    public const int WIDTH = 100;
    /** ViewBox height the points are projected into. */
    public const int HEIGHT = 28;

    public function __construct(
        /** Number of days plotted (up to 30). */
        public int $days,
        /** SVG polyline points for the temperature curve ("x,y x,y …"). */
        public string $temperaturePoints,
        /** SVG polyline points for the cloud-cover curve (0-100 % fixed scale). */
        public string $cloudPoints,
        public string $temperatureMinLabel,
        public string $temperatureMaxLabel,
    ) {
    }
}
