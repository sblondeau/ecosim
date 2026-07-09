<?php

declare(strict_types=1);

namespace App\Domain\Simulation;

use App\Domain\Building\Household;
use App\Domain\Finance\Money;
use DateTimeImmutable;

/**
 * Estimates what a full year in a given house configuration costs and yields,
 * by running the REAL simulation engine over a reference weather year — no
 * duplicated formulas, so a quote's promise can never drift from what the
 * game actually simulates.
 *
 * The reference year uses a fixed seed, deliberately NOT the current game's:
 * quoting the player's actual future weather would hand them an oracle. So
 * the estimate is honest ("une année météo type") but never exact — which is
 * also true of real-life quotes.
 */
final readonly class AnnualOutcomeEstimator
{
    /** Any fixed seed works — it only has to be the same for every quote. */
    private const int REFERENCE_SEED = 20250101;

    public function __construct(
        // No scripted events: they belong to the game, not to the estimate.
        private SimulationEngine $engine = new SimulationEngine(events: []),
    ) {
    }

    public function estimate(Household $household): AnnualOutcome
    {
        $config = new GameConfig(
            seed: self::REFERENCE_SEED,
            epoch: new DateTimeImmutable('2025-01-01'),
            horizonDays: 365,
        );

        $state = GameState::start($household, Money::zero());
        while (!$this->engine->isFinished($config, $state)) {
            $state = $this->engine->advance($config, $state);
        }

        return new AnnualOutcome(
            netEnergyCost: $state->totals->netEnergyCost(),
            averageComfortScore: $state->totals->averageComfortScore(),
            productionKwh: $state->totals->productionKwh,
            selfSufficiencyRatio: $state->totals->selfSufficiencyRatio(),
        );
    }
}
