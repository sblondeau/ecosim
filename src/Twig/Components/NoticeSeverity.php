<?php

declare(strict_types=1);

namespace App\Twig\Components;

/** How a {@see Notice} banner reads: a confirmation or a refusal. */
enum NoticeSeverity: string
{
    case Success = 'success';
    case Error = 'error';

    public function icon(): string
    {
        return match ($this) {
            self::Success => '✅',
            self::Error => '⚠️',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Success => 'banner-ok',
            self::Error => '',
        };
    }
}
