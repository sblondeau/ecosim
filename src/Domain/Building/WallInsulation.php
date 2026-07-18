<?php

declare(strict_types=1);

namespace App\Domain\Building;

/** How the exterior walls are insulated (Tranche 1 tech tree). */
enum WallInsulation: string
{
    case None = 'none';
    /** Intérieure (ITI): cheaper, eats living space, residual thermal bridges. */
    case Interior = 'interior';
    /** Extérieure (ITE): dearer, no thermal bridge, keeps living space. */
    case Exterior = 'exterior';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Non isolés',
            self::Interior => 'Intérieure (ITI)',
            self::Exterior => 'Extérieure (ITE)',
        };
    }
}
