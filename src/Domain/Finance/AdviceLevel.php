<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/** How strongly a renovation advice reads — never prescriptive, only informative or a caution. */
enum AdviceLevel: string
{
    case Info = 'info';
    case Caution = 'caution';

    public function icon(): string
    {
        return match ($this) {
            self::Info => '💡',
            self::Caution => '⚠️',
        };
    }
}
