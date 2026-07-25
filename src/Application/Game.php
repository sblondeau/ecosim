<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Time\TimeProgression;

use function in_array;

/**
 * A loaded game: its immutable configuration, its current state, the real-time
 * progression (how far the wall clock has been accounted for, at which
 * player-chosen speed), and which one-shot scenario modals the player has
 * already dismissed.
 *
 * A thin pairing used by the application layer to move the parts around
 * together (load, advance, save) without leaking any into the presentation.
 */
final readonly class Game
{
    /**
     * @param list<string> $acknowledgedEvents ids of scenario modals dismissed
     *                                         (persisted, so a page refresh does
     *                                         not re-show the intro/briefing)
     */
    public function __construct(
        public GameConfig $config,
        public GameState $state,
        public TimeProgression $progression,
        public array $acknowledgedEvents = [],
    ) {
    }

    public function withState(GameState $state): self
    {
        return new self($this->config, $state, $this->progression, $this->acknowledgedEvents);
    }

    public function withProgression(TimeProgression $progression): self
    {
        return new self($this->config, $this->state, $progression, $this->acknowledgedEvents);
    }

    public function withAcknowledgedEvent(string $eventId): self
    {
        if (in_array($eventId, $this->acknowledgedEvents, true)) {
            return $this;
        }

        return new self($this->config, $this->state, $this->progression, [...$this->acknowledgedEvents, $eventId]);
    }
}
