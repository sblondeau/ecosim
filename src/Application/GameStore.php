<?php

declare(strict_types=1);

namespace App\Application;

/**
 * Loads and persists the current game.
 *
 * The presentation layer ({@see \App\Controller\GameController}) depends on
 * this abstraction, never on a concrete storage mechanism — so swapping the
 * session-backed {@see SessionGameStore} for a Doctrine-backed store later is
 * a matter of writing one new class, with zero change to the controller.
 */
interface GameStore
{
    /**
     * The game in progress, or a fresh one if none has been started yet.
     */
    public function current(): Game;

    public function save(Game $game): void;

    /**
     * Start a new game with the default Phase 0-1 equipment and store it.
     */
    public function reset(): Game;
}
