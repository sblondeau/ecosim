<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;

/**
 * A loaded game: its immutable configuration plus its current state.
 *
 * A thin pairing used by the application layer to move both halves around
 * together (load, advance, save) without leaking either into the presentation.
 */
final readonly class Game
{
    public function __construct(
        public GameConfig $config,
        public GameState $state,
    ) {
    }

    public function withState(GameState $state): self
    {
        return new self($this->config, $state);
    }
}
