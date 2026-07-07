<?php

declare(strict_types=1);

namespace App\Domain\Building;

use InvalidArgumentException;

/**
 * The full configuration of the player's house: production equipment, storage,
 * envelope and heating system.
 *
 * Everything here can change as the game is played (installing panels,
 * renovating, replacing the boiler are the game's decisions — game-design §8,
 * §18), so the household lives inside the game state, not the game config.
 * Immutable value object: a renovation produces a new Household.
 */
final readonly class Household
{
    public function __construct(
        /** Installed solar peak power, in kWc (0 = none). */
        public float $solarKwc,
        /** Battery usable capacity, in kWh (0 = none). */
        public float $batteryKwh,
        public InsulationLevel $insulation,
        public HeatingSystem $heatingSystem,
    ) {
        if ($solarKwc < 0.0) {
            throw new InvalidArgumentException("Solar power cannot be negative: {$solarKwc}.");
        }

        if ($batteryKwh < 0.0) {
            throw new InvalidArgumentException("Battery capacity cannot be negative: {$batteryKwh}.");
        }
    }

    public function dpeClass(): DpeClass
    {
        return DpeClass::fromBuilding($this->insulation, $this->heatingSystem);
    }
}
