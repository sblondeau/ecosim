<?php

declare(strict_types=1);

namespace App\Domain\Time;

/**
 * The player-chosen pace of real-time progression. The backed value IS the
 * speed multiplier applied to the base pace (1 game day per
 * {@see TimeProgression::SECONDS_PER_GAME_DAY} real seconds); Paused stops
 * the clock entirely.
 */
enum TickSpeed: int
{
    case Paused = 0;
    case Normal = 1;
    case Double = 2;
    case Triple = 3;

    public function multiplier(): int
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Paused => 'Pause',
            self::Normal => '×1',
            self::Double => '×2',
            self::Triple => '×3',
        };
    }
}
