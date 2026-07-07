<?php

declare(strict_types=1);

namespace App\Domain\Building;

/**
 * The three discrete insulation tiers of the Phase 0-1 scope (game-design §15).
 *
 * The detailed insulation tech tree (materials, wall/roof split, external
 * insulation…) is deliberately deferred to V2 — the MVP models insulation as
 * a single envelope quality that scales the heating need and the cold-wall
 * discomfort (coefficients in {@see BuildingCalibration}).
 */
enum InsulationLevel: string
{
    case None = 'none';
    case Retrofitted = 'retrofitted';
    case Reinforced = 'reinforced';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Aucune',
            self::Retrofitted => 'Correcte',
            self::Reinforced => 'Performante',
        };
    }
}
