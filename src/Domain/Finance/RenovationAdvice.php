<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * A non-prescriptive piece of advice about a renovation, given the current
 * house state: an informative repère (Info) or a caution against a genuine
 * sequencing mistake (Caution). Never a "do this now" — the player keeps the
 * choice (game-design: pédagogie par les systèmes, pas de dirigisme).
 */
final readonly class RenovationAdvice
{
    public function __construct(
        public AdviceLevel $level,
        public string $message,
    ) {
    }
}
