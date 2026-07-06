<?php

declare(strict_types=1);

namespace App\Domain\Time;

use InvalidArgumentException;

/**
 * Meteorological seasons for the northern hemisphere (France).
 *
 * Phase 0-1 uses seasons only to shape the seasonal temperature sinusoid
 * (game-design §5, layer 2 "saisonnalité"). Boundaries follow the standard
 * meteorological convention (whole months) rather than astronomical solstices,
 * which is precise enough for a daily tick and simpler to reason about.
 */
enum Season: string
{
    case Winter = 'winter';
    case Spring = 'spring';
    case Summer = 'summer';
    case Autumn = 'autumn';

    /**
     * @param int<1, 12> $month
     */
    public static function fromMonth(int $month): self
    {
        return match ($month) {
            12, 1, 2 => self::Winter,
            3, 4, 5 => self::Spring,
            6, 7, 8 => self::Summer,
            9, 10, 11 => self::Autumn,
            default => throw new InvalidArgumentException("Invalid month: {$month}"),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Winter => 'Hiver',
            self::Spring => 'Printemps',
            self::Summer => 'Été',
            self::Autumn => 'Automne',
        };
    }
}
