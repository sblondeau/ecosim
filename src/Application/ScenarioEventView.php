<?php

declare(strict_types=1);

namespace App\Application;

/**
 * A scenario event that has occurred and may still need a one-shot modal
 * (see {@see \App\Domain\Scenario\ExplainedEvent}). The modal's own content
 * lives in `templates/game/scenario_event/_<id>.html.twig`, not here.
 */
final readonly class ScenarioEventView
{
    public function __construct(
        public string $id,
        public bool $restartsClockOnAcknowledge,
    ) {
    }
}
