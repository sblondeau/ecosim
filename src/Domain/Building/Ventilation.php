<?php

declare(strict_types=1);

namespace App\Domain\Building;

/** Air renewal strategy — 4ᵉ surface de l'enveloppe (arbre travaux, Tranche 5). */
enum Ventilation: string
{
    case None = 'none';
    /** VMC double flux : récupère la chaleur de l'air extrait avant de le rejeter. */
    case DoubleFlow = 'double_flow';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Aucune (naturelle)',
            self::DoubleFlow => 'VMC double flux',
        };
    }
}
