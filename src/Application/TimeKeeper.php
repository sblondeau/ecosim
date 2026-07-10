<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Simulation\SimulationEngine;
use App\Domain\Time\TickSpeed;
use DateTimeImmutable;

/**
 * Catches a game up with the real clock: converts the elapsed wall time into
 * due game days (via the domain's {@see \App\Domain\Time\TimeProgression})
 * and lives them through the engine, stopping at the horizon.
 *
 * Every entry point that shows or mutates the game (dashboard render, poll,
 * player actions) calls this first, so game time only ever flows through one
 * door.
 *
 * Dramatic scripted moments auto-pause: when the boiler breakdown fires, the
 * catch-up stops on that very morning and the speed drops to Paused — the
 * player reads, compares the quotes and decides, then resumes.
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

        return $this->live($game->withProgression($tick->progression), $tick->days, $now);
    }

    /**
     * Manual step: live the current day now, whatever the clock says. The
     * real-time clock restarts so the manual day is not double-counted —
     * unless the catch-up itself just hit the breakdown, which takes priority.
     */
    public function step(Game $game, DateTimeImmutable $now): Game
    {
        $wasBroken = $game->state->household->boilerBroken;
        $game = $this->catchUp($game, $now);

        if (!$wasBroken && $game->state->household->boilerBroken) {
            return $game;
        }

        $game = $this->live($game, 1, $now);

        return $game->withProgression($game->progression->withSpeed($game->progression->speed, $now));
    }

    private function live(Game $game, int $days, DateTimeImmutable $now): Game
    {
        $state = $game->state;
        $progression = $game->progression;

        for ($day = 0; $day < $days && !$this->engine->isFinished($game->config, $state); ++$day) {
            $wasBroken = $state->household->boilerBroken;
            $state = $this->engine->advance($game->config, $state);

            if (!$wasBroken && $state->household->boilerBroken) {
                // The breakdown morning: freeze right there, drop the rest of
                // the batch — deciding deserves a stopped clock.
                $progression = $progression->withSpeed(TickSpeed::Paused, $now);
                break;
            }
        }

        return new Game($game->config, $state, $progression);
    }
}
