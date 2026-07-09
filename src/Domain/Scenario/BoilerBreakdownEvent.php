<?php

declare(strict_types=1);

namespace App\Domain\Scenario;

use App\Domain\Building\HeatingSystem;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;

/**
 * The old fuel-oil boiler dies on a chosen morning — but only if it is still
 * there. A player who already switched to the heat pump never lives it, and a
 * repaired boiler holds for the rest of the game (strict day equality: the
 * event fires once, it is a scene, not a wear model).
 */
final readonly class BoilerBreakdownEvent implements ScriptedEvent
{
    public function __construct(
        /** Day index of the breakdown morning. */
        private int $day,
    ) {
    }

    public function shouldFire(GameConfig $config, GameState $state): bool
    {
        return $state->currentDay === $this->day
            && HeatingSystem::FuelOilBoiler === $state->household->heatingSystem
            && !$state->household->boilerBroken;
    }

    public function fire(GameState $state): GameState
    {
        return $state->withHousehold($state->household->withBoilerBroken(true));
    }
}
