<?php

declare(strict_types=1);

namespace App\Domain\Building;

/** Window glazing tier (Tranche 1 tech tree). */
enum Glazing: string
{
    case Single = 'single';
    case Double = 'double';
    case Triple = 'triple';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Simple vitrage',
            self::Double => 'Double vitrage',
            self::Triple => 'Triple vitrage',
        };
    }
}
