<?php

declare(strict_types=1);

namespace App\Domain\Scenario;

use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;

/**
 * A scripted scenario event: something that happens TO the player at a chosen
 * moment (game-design §15 — scenes, not wear models).
 *
 * The trigger is an arbitrary predicate over the game — a day index, an
 * equipment state, a weather condition (recomputable from the seed, so
 * determinism holds), a savings threshold… whatever the scenario needs.
 * Events are standalone so several scenarios can reuse one, parameterised
 * differently.
 */
interface ScriptedEvent
{
    /**
     * Should the event happen this morning? Evaluated after each day is
     * settled; must be pure (no clock, no randomness beyond the seeded state)
     * so a game stays entirely replayable.
     */
    public function shouldFire(GameConfig $config, GameState $state): bool;

    public function fire(GameState $state): GameState;
}
