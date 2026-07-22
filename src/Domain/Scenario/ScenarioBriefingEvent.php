<?php

declare(strict_types=1);

namespace App\Domain\Scenario;

use App\Domain\Simulation\GameState;

/**
 * The second onboarding screen, shown right after {@see ScenarioIntroEvent}:
 * the four axes the player balances and how to act. Split from the intro so the
 * first modal stays pure situation (immersion) and this one carries the
 * mechanics — neither drowns the other.
 *
 * Like the intro it is not a {@see ScriptedEvent}: there is nothing to fire, it
 * is relevant from the very first render and stays that way until acknowledged.
 * Being the LAST modal before play, it is the one that restarts the real-time
 * clock, so time spent reading both screens is never burned as game days.
 */
final readonly class ScenarioBriefingEvent implements ExplainedEvent
{
    public function id(): string
    {
        return 'briefing';
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
