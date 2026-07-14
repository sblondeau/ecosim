<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Application\Game;
use App\Application\GameStore;
use App\Application\GameView;
use App\Application\GameViewFactory;
use App\Application\RenovationHandler;
use App\Application\TimeKeeper;
use App\Domain\Building\BuildingCalibration;
use App\Domain\Finance\Renovation;
use App\Domain\Simulation\GameState;
use App\Domain\Time\TickSpeed;
use DateTimeImmutable;

use function in_array;
use function max;
use function min;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The whole dashboard as a single live component (game-design §3): the scene,
 * the panels and every player action live here. `data-poll` re-renders it and
 * each render first catches the game up with the real clock; player actions are
 * {@see LiveAction}s that mutate the persisted game and re-render in place — no
 * page reload, one door for game time ({@see TimeKeeper}).
 *
 * Transient feedback (a refused renovation, "Travaux réalisés !") is a
 * component property rather than a session flash, since the component
 * re-renders without a full request cycle.
 */
#[AsLiveComponent]
final class GameDashboard
{
    use DefaultActionTrait;

    /** Clickable equipment slots, plus 'menu' for the totals/patrimoine drawer. */
    private const array PANELS = ['roof', 'walls', 'heating', 'garage', 'living', 'menu'];

    /** The floating panel currently open over the scene (null = none, fullwidth). */
    #[LiveProp(writable: true)]
    public ?string $selectedSlot = null;

    /** Transient message shown after an action (persists across polls, cleared on the next action). */
    #[LiveProp(writable: true)]
    public string $notice = '';

    #[LiveProp(writable: true)]
    public bool $noticeIsError = false;

    /**
     * The welcome overlay shows on a brand-new game (day 1) until dismissed.
     * A LiveProp, so it survives polls; it naturally reappears only for a fresh
     * game (the template also gates on day 1), never mid-run once you've played.
     */
    #[LiveProp(writable: true)]
    public bool $introDismissed = false;

    private ?Game $game = null;
    private ?GameView $view = null;

    public function __construct(
        private readonly GameStore $store,
        private readonly TimeKeeper $timeKeeper,
        private readonly GameViewFactory $viewFactory,
        private readonly RenovationHandler $renovations,
        private readonly BuildingCalibration $building = new BuildingCalibration(),
    ) {
    }

    #[LiveAction]
    public function selectSlot(#[LiveArg] string $slot): void
    {
        $this->notice = '';

        if (!in_array($slot, self::PANELS, true)) {
            return;
        }

        // Clicking the open panel's trigger again closes it.
        $this->selectedSlot = $slot === $this->selectedSlot ? null : $slot;
    }

    #[LiveAction]
    public function closePanel(): void
    {
        $this->notice = '';
        $this->selectedSlot = null;
    }

    /**
     * Dismiss the welcome overlay and start playing. The real-time clock is
     * restarted from now, so the seconds spent reading the intro don't burn
     * game days (polling was paused while the overlay was up).
     */
    #[LiveAction]
    public function dismissIntro(): void
    {
        $this->introDismissed = true;
        $game = $this->store->current();
        $this->commit($game->withProgression($game->progression->withSpeed($game->progression->speed, new DateTimeImmutable())));
    }

    /** Manual step: live the current day now, restarting the real-time clock. */
    #[LiveAction]
    public function step(): void
    {
        $this->notice = '';
        $this->commit($this->timeKeeper->step($this->store->current(), new DateTimeImmutable()));
    }

    #[LiveAction]
    public function setSpeed(#[LiveArg] int $speed): void
    {
        $this->notice = '';
        $chosen = TickSpeed::tryFrom($speed);
        if (null === $chosen) {
            return;
        }

        $now = new DateTimeImmutable();
        $game = $this->timeKeeper->catchUp($this->store->current(), $now);
        $this->commit($game->withProgression($game->progression->withSpeed($chosen, $now)));
    }

    #[LiveAction]
    public function adjustSetpoint(#[LiveArg] int $delta): void
    {
        $this->notice = '';
        $game = $this->timeKeeper->catchUp($this->store->current(), new DateTimeImmutable());
        $household = $game->state->household;

        $target = $household->heatingSetpointC + max(-1, min(1, $delta));
        $clamped = max(
            $this->building->minHeatingSetpointC()->value,
            min($this->building->maxHeatingSetpointC()->value, $target),
        );

        $this->commit($game->withState($game->state->withHousehold($household->withHeatingSetpointC($clamped))));
    }

    #[LiveAction]
    public function order(#[LiveArg] string $work, #[LiveArg] string $financing): void
    {
        $this->notice = '';

        $renovation = Renovation::tryFrom($work);
        if (null === $renovation) {
            $this->fail('Travaux inconnus.');

            return;
        }

        $game = $this->timeKeeper->catchUp($this->store->current(), new DateTimeImmutable());
        $result = $this->renovations->order($game->state, $renovation, $financing);

        if (!$result instanceof GameState) {
            $this->commit($game); // keep the caught-up days
            $this->fail($result);

            return;
        }

        $this->commit($game->withState($result));
        $this->notice = 'Travaux réalisés !';
        $this->noticeIsError = false;
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->selectedSlot = null;
        $this->notice = '';
        $this->game = $this->store->reset();
        $this->view = null;
    }

    public function getGame(): GameView
    {
        return $this->view ??= $this->viewFactory->build($this->loaded()->config, $this->loaded()->state);
    }

    public function getSpeedValue(): int
    {
        return $this->loaded()->progression->speed->value;
    }

    private function fail(string $message): void
    {
        $this->notice = $message;
        $this->noticeIsError = true;
    }

    /** Persist the mutated game and make it the render source (skips a re-catch-up). */
    private function commit(Game $game): void
    {
        $this->store->save($game);
        $this->game = $game;
        $this->view = null;
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
