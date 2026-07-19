<?php

declare(strict_types=1);

namespace App\Twig\Components;

/**
 * The six axis/meta panels that pop over a corner or edge of the scene, as
 * opposed to the five clickable house zones ({@see \App\Domain\Finance\SceneSlot}).
 * Presentation-only: no domain logic reads these.
 */
enum AxisPanel: string
{
    case Finances = 'finances';
    case Comfort = 'comfort';
    case Energy = 'energy';
    case Patrimoine = 'patrimoine';
    case Weather = 'weather';
    case Options = 'options';

    /** Where the floating panel docks around the scene. */
    public function anchor(): string
    {
        return match ($this) {
            self::Finances => 'at-tl',
            self::Comfort => 'at-tr',
            self::Energy => 'at-bl',
            self::Patrimoine => 'at-br',
            self::Weather => 'at-top',
            self::Options => 'at-bottom',
        };
    }
}
