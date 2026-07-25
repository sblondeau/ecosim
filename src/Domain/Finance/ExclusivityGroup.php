<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * A set of works that replace the SAME exclusive equipment, so at most one of
 * them may have a chantier in flight at a time — you pick a single heating
 * generator, you do not queue a heat pump AND a pellet boiler.
 *
 * A coherence rule, not an artificial gate (game-design §1): once a generator
 * is installed you may still switch to another later (the coût d'accès stays
 * the full price). It only forbids ordering a second, conflicting chantier
 * while the first is still being built.
 */
enum ExclusivityGroup: string
{
    case HeatingGenerator = 'heating-generator';
}
