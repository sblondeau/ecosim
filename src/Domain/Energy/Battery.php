<?php

declare(strict_types=1);

namespace App\Domain\Energy;

use InvalidArgumentException;

/**
 * Battery configuration (game-design §8, §15 — one capacity in Phase 0-1).
 *
 * A value object describing the hardware, not its live charge level (the level
 * is game state, carried separately and threaded through the tick). Charge and
 * discharge each lose energy, so that charge × discharge equals the round-trip
 * efficiency.
 */
final readonly class Battery
{
    public function __construct(
        public float $capacityKwh,
        public float $chargeEfficiency,
        public float $dischargeEfficiency,
    ) {
        if ($capacityKwh < 0.0) {
            throw new InvalidArgumentException("Battery capacity cannot be negative: {$capacityKwh}.");
        }

        foreach (['charge' => $chargeEfficiency, 'discharge' => $dischargeEfficiency] as $name => $efficiency) {
            if ($efficiency <= 0.0 || $efficiency > 1.0) {
                throw new InvalidArgumentException("Battery {$name} efficiency must be within (0, 1], got {$efficiency}.");
            }
        }
    }

    /**
     * No battery installed.
     */
    public static function none(): self
    {
        return new self(0.0, 1.0, 1.0);
    }

    /**
     * A battery with a symmetric one-way efficiency on charge and discharge.
     */
    public static function of(float $capacityKwh, float $oneWayEfficiency): self
    {
        return new self($capacityKwh, $oneWayEfficiency, $oneWayEfficiency);
    }

    public function isInstalled(): bool
    {
        return $this->capacityKwh > 0.0;
    }
}
