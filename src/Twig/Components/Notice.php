<?php

declare(strict_types=1);

namespace App\Twig\Components;

/** Transient feedback shown after a player action (see {@see GameDashboard::$notice}). */
final readonly class Notice
{
    public function __construct(
        public string $text,
        public NoticeSeverity $severity,
    ) {
    }

    public static function success(string $text): self
    {
        return new self($text, NoticeSeverity::Success);
    }

    public static function error(string $text): self
    {
        return new self($text, NoticeSeverity::Error);
    }
}
