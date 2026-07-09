<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Simulation\SimulationEngine;
use DateTimeImmutable;

/**
 * Catches a game up with the real clock: converts the elapsed wall time into
 * due game days (via the domain's {@see \App\Domain\Time\TimeProgression})
 * and lives them through the engine, stopping at the horizon.
 *
 * Every entry point that shows or mutates the game (dashboard render, poll,
 * player actions) calls this first, so game time only ever flows through one
 * door.
 */
final readonly class TimeKeeper
{
    public function __construct(
        private SimulationEngine $engine = new SimulationEngine(),
    ) {
    }

    public function catchUp(Game $game, DateTimeImmutable $now): Game
    {
        $tick = $game->progression->tick($now);

        $state = $game->state;
        for ($day = 0; $day < $tick->days && !$this->engine->isFinished($game->config, $state); ++$day) {
            $state = $this->engine->advance($game->config, $state);
        }

        return new Game($game->config, $state, $tick->progression);
    }
}
