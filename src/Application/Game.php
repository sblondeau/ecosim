<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Time\TimeProgression;

/**
 * A loaded game: its immutable configuration, its current state, and the
 * real-time progression (how far the wall clock has been accounted for, at
 * which player-chosen speed).
 *
 * A thin pairing used by the application layer to move the halves around
 * together (load, advance, save) without leaking any into the presentation.
 */
final readonly class Game
{
    public function __construct(
        public GameConfig $config,
        public GameState $state,
        public TimeProgression $progression,
    ) {
    }

    public function withState(GameState $state): self
    {
        return new self($this->config, $state, $this->progression);
    }

    public function withProgression(TimeProgression $progression): self
    {
        return new self($this->config, $this->state, $progression);
    }
}
