<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable configuration of a single playthrough (game-design §15).
 *
 * Phase 0-1 has one scenario, so this is small: the weather seed, the calendar
 * epoch, the installed equipment (one solar size, one battery size) and the
 * fixed horizon at which the game ends. It is framework-free and fully
 * determines a game together with a {@see GameState}.
 */
final readonly class GameConfig
{
    /**
     * @param int          $seed        weather seed (same seed = same weather)
     * @param float        $solarKwc    installed solar peak power (0 = none)
     * @param float        $batteryKwh  battery usable capacity (0 = none)
     * @param positive-int $horizonDays number of days the game runs before the final report
     */
    public function __construct(
        public int $seed,
        public DateTimeImmutable $epoch,
        public float $solarKwc,
        public float $batteryKwh,
        public int $horizonDays,
    ) {
        if ($solarKwc < 0.0) {
            throw new InvalidArgumentException("Solar power cannot be negative: {$solarKwc}.");
        }

        if ($batteryKwh < 0.0) {
            throw new InvalidArgumentException("Battery capacity cannot be negative: {$batteryKwh}.");
        }

        if ($horizonDays < 1) {
            throw new InvalidArgumentException("Horizon must be at least one day: {$horizonDays}.");
        }
    }
}
