<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable configuration of a single playthrough (game-design §15).
 *
 * Phase 0-1 has one scenario, so this is small: the weather seed, the calendar
 * epoch and the fixed horizon at which the game ends — whatever is truly fixed
 * for the whole game and never changes once it starts. Installed equipment
 * (solar, battery) is NOT here: the player can install/change it as the game
 * is played, so it lives in {@see GameState} instead (game-design §8, §18).
 */
final readonly class GameConfig
{
    /**
     * @param int          $seed        weather seed (same seed = same weather)
     * @param positive-int $horizonDays number of days the game runs before the final report
     */
    public function __construct(
        public int $seed,
        public DateTimeImmutable $epoch,
        public int $horizonDays,
    ) {
        if ($horizonDays < 1) {
            throw new InvalidArgumentException("Horizon must be at least one day: {$horizonDays}.");
        }
    }
}
