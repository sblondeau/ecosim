<?php

declare(strict_types=1);

namespace App\Domain\Building;

/**
 * The three discrete insulation tiers of the Phase 0-1 scope (game-design §15).
 *
 * `Original` is the unrenovated state of the scenario's old house — poor
 * original insulation, not literally zero (walls always resist a little); it
 * is the calibration reference (factor 1.0 in
 * {@see BuildingCalibration::insulationFactor()}). The game-design wording
 * « aucune » was amended to « d'origine » for physical honesty.
 *
 * The detailed insulation tech tree (materials, wall/roof split, external
 * insulation…) is deliberately deferred to V2 — the MVP models insulation as
 * a single envelope quality that scales the heating need and the cold-wall
 * discomfort.
 */
enum InsulationLevel: string
{
    case Original = 'original';
    case Retrofitted = 'retrofitted';
    case Reinforced = 'reinforced';

    public function label(): string
    {
        return match ($this) {
            self::Original => 'D\'origine',
            self::Retrofitted => 'Correcte',
            self::Reinforced => 'Performante',
        };
    }
}
