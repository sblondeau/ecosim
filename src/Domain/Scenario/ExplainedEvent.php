<?php

declare(strict_types=1);

namespace App\Domain\Scenario;

use App\Domain\Simulation\GameState;

/**
 * A {@see ScriptedEvent} the player needs explaining — it earns a one-shot
 * modal instead of quietly changing the scene. Kept separate from
 * `ScriptedEvent` because not every scripted event needs a modal, and this one
 * doesn't need to be "fired" by the tick loop at all (see
 * {@see ScenarioIntroEvent}, which has nothing to do at day 0 besides being
 * shown).
 */
interface ExplainedEvent
{
    /**
     * Stable identity: names the Twig partial
     * (`game/scenario_event/_<id>.html.twig`) and the acknowledgement
     * LiveAction's argument. Unique across a scenario's explained events.
     */
    public function id(): string;

    /**
     * Whether this event's effect is currently in place. Re-derived from the
     * state the same way the domain already reads it elsewhere (e.g. the
     * boiler breakdown from `Household::$boilerBroken`) — no separate "seen"
     * ledger to keep in sync.
     */
    public function hasOccurred(GameState $state): bool;

    /**
     * Whether acknowledging this event's modal should restart the real-time
     * clock from now (so time spent reading the modal isn't burned as game
     * days — see the intro, which shows before the clock has ever been
     * meaningfully started).
     */
    public function restartsClockOnAcknowledge(): bool;
}
