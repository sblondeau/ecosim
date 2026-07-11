<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Application\Game;
use App\Application\GameStore;
use App\Application\GameView;
use App\Application\GameViewFactory;
use App\Application\TimeKeeper;
use DateTimeImmutable;

use function in_array;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The live dashboard: re-rendered by `data-poll`, each render first catches
 * the game up with the real clock (the acted tick decision: time flows while
 * the page is watched, at the player-chosen speed).
 *
 * Presentation-only glue — it loads, catches up, saves, and hands the
 * template a {@see GameView}. No game logic lives here.
 */
#[AsLiveComponent]
final class GameDashboard
{
    use DefaultActionTrait;

    private const array SLOTS = ['roof', 'walls', 'heating', 'garage', 'living'];

    /** The scene slot whose contextual panel is open (null = summary panel). */
    #[LiveProp(writable: true)]
    public ?string $selectedSlot = null;

    private ?Game $game = null;
    private ?GameView $view = null;

    public function __construct(
        private readonly GameStore $store,
        private readonly TimeKeeper $timeKeeper,
        private readonly GameViewFactory $viewFactory,
    ) {
    }

    #[LiveAction]
    public function selectSlot(#[LiveArg] string $slot): void
    {
        if (!in_array($slot, self::SLOTS, true)) {
            return;
        }

        // Clicking the selected slot again closes its panel.
        $this->selectedSlot = $slot === $this->selectedSlot ? null : $slot;
    }

    #[LiveAction]
    public function closePanel(): void
    {
        $this->selectedSlot = null;
    }

    public function getGame(): GameView
    {
        return $this->view ??= $this->viewFactory->build($this->loaded()->config, $this->loaded()->state);
    }

    public function getSpeedValue(): int
    {
        return $this->loaded()->progression->speed->value;
    }

    private function loaded(): Game
    {
        if (null === $this->game) {
            $this->game = $this->timeKeeper->catchUp($this->store->current(), new DateTimeImmutable());
            $this->store->save($this->game);
        }

        return $this->game;
    }
}
