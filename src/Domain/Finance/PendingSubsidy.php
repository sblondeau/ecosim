<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * A renovation subsidy (MaPrimeRénov'-like) owed to the household but not yet
 * paid: the amount, and the game-day it lands as cash (§ contrainte, levier ②).
 *
 * The real scheme pays the prime AFTER the works, once the file is processed —
 * so at order time the household fronts the full sticker price and the prime
 * comes back weeks later. This VO carries that deferred credit; the engine
 * banks it on {@see self::$disbursementDay}.
 */
final readonly class PendingSubsidy
{
    public function __construct(
        public Money $amount,
        /** Game-day index on which the prime is credited to savings. */
        public int $disbursementDay,
    ) {
    }
}
