<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * The five clickable zones of the house cutaway, and the drawer each work is
 * offered in.
 *
 * Deliberately an enum, unlike the works themselves: this set is genuinely
 * closed — the zones are fixed by the artwork, and adding one means redrawing
 * the house. Here `match` exhaustiveness is a benefit at no cost.
 */
enum SceneSlot: string
{
    case Roof = 'roof';
    case Walls = 'walls';
    case Heating = 'heating';
    case Garage = 'garage';
    case Living = 'living';
}
