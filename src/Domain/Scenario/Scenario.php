<?php

declare(strict_types=1);

namespace App\Domain\Scenario;

use App\Domain\Simulation\GameState;

/**
 * A playable scenario: where a game starts and what is scripted to happen.
 *
 * One scenario exists in Phase 0-1 ({@see PrimoAccedantScenario}); the
 * interface is the seam the next ones (locataire, V2…) plug into. Deliberately
 * small: end conditions beyond the fixed horizon (game-design §15 mentions
 * threshold-based endings) and any scenario↔game-mode mapping (Phase 6+,
 * échelles ville/pays) will join HERE when a scenario actually needs them —
 * not before.
 */
interface Scenario
{
    /** Day 0: household, savings, empty totals. */
    public function initialState(): GameState;

    /**
     * Days the game runs before the factual end report.
     *
     * @return positive-int
     */
    public function horizonDays(): int;

    /**
     * The scripted events of this scenario, evaluated each settled morning.
     *
     * @return list<ScriptedEvent>
     */
    public function events(): array;
}
