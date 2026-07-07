<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\DpeClass;

/**
 * Property value from the DPE class — the simple formula of the Phase 0-1
 * scope (game-design §15: « valeur du bien via une formule simple liée au
 * DPE, pas de marché immobilier dynamique »).
 *
 * The scenario house was BOUGHT as a G passoire: its purchase price is the
 * reference, and each DPE class gained adds a sourced percentage (Notaires de
 * France: ~+8%/class for houses, §8). This wealth is the second, NON-LIQUID
 * payoff channel of a renovation — realisable only on resale, displayed apart
 * from the bill savings (multi-criteria, §1).
 */
final readonly class PropertyValuator
{
    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
    ) {
    }

    public function valueFor(DpeClass $dpe): Money
    {
        $purchase = $this->calibration->housePurchasePrice()->value;
        $stepPct = $this->calibration->dpeClassValueStep()->value;

        return Money::fromEuros($purchase * (1.0 + $stepPct * $dpe->stepsAboveWorst()));
    }
}
