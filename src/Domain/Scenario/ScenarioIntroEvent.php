<?php

declare(strict_types=1);

namespace App\Domain\Scenario;

use App\Domain\Simulation\GameState;

/**
 * The scenario's own presentation: the household's starting situation and
 * goal, shown before the player takes their first action. Not a
 * {@see ScriptedEvent} — there is nothing to fire, it is relevant from the
 * very first render and stays that way until acknowledged.
 */
final readonly class ScenarioIntroEvent implements ExplainedEvent
{
    public function id(): string
    {
        return 'intro';
    }

    public function hasOccurred(GameState $state): bool
    {
        return true;
    }

    public function restartsClockOnAcknowledge(): bool
    {
        return true;
    }
}
