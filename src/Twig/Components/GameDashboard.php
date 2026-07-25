<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Application\Game;
use App\Application\GameStore;
use App\Application\GameView;
use App\Application\GameViewFactory;
use App\Application\RenovationHandler;
use App\Application\ScenarioEventView;
use App\Application\TimeKeeper;
use App\Domain\Building\BuildingCalibration;
use App\Domain\Finance\RenovationCatalog;
use App\Domain\Finance\SceneSlot;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\ScheduledWork;
use App\Domain\Time\TickSpeed;

use function array_diff;
use function array_filter;
use function array_map;
use function array_values;
use function count;

use DateTimeImmutable;

use function implode;
use function in_array;
use function max;
use function min;
use function sprintf;

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

    /** The floating panel currently open over the scene (null = none, fullwidth). */
    #[LiveProp(writable: true)]
    public ?string $selectedSlot = null;

    /** Transient message shown after an action (persists across polls, cleared on the next action). */
    #[LiveProp(writable: true, useSerializerForHydration: true)]
    public ?Notice $notice = null;

    /**
     * Ids of the scenario events ({@see ScenarioEventView}) whose one-shot
     * modal the player has already closed. A LiveProp, so it survives polls;
     * reset on a new game so a fresh playthrough shows them again.
     *
     * @var list<string>
     */
    #[LiveProp(writable: true)]
    public array $acknowledgedEvents = [];

    private ?Game $game = null;
    private ?GameView $view = null;

    public function __construct(
        private readonly GameStore $store,
        private readonly TimeKeeper $timeKeeper,
        private readonly GameViewFactory $viewFactory,
        private readonly RenovationHandler $renovations,
        private readonly RenovationCatalog $catalog = new RenovationCatalog(),
        private readonly BuildingCalibration $building = new BuildingCalibration(),
    ) {
    }

    /**
     * On the initial page render, seed the acknowledged-modals prop from the
     * PERSISTED game — so a refresh does not re-show the intro/briefing the
     * player already dismissed (polls keep it via the LiveProp; only a full
     * reload re-mounts and would otherwise reset it to []).
     */
    public function mount(): void
    {
        $this->acknowledgedEvents = $this->store->current()->acknowledgedEvents;
    }

    #[LiveAction]
    public function selectSlot(#[LiveArg] string $slot): void
    {
        $this->notice = null;

        if (null === SceneSlot::tryFrom($slot) && null === AxisPanel::tryFrom($slot)) {
            return;
        }

        // Clicking the open panel's trigger again closes it.
        $this->selectedSlot = $slot === $this->selectedSlot ? null : $slot;
    }

    #[LiveAction]
    public function closePanel(): void
    {
        $this->notice = null;
        $this->selectedSlot = null;
    }

    /**
     * Close a scenario event's one-shot modal. Some events (the intro) want
     * the real-time clock restarted from now, so time spent reading doesn't
     * burn game days — others (the boiler breakdown) leave it as is, already
     * paused by {@see TimeKeeper}.
     */
    #[LiveAction]
    public function acknowledgeEvent(#[LiveArg('id')] string $eventId): void
    {
        $this->acknowledgedEvents[] = $eventId;

        // Persist the acknowledgement in the game itself, so a page refresh
        // remembers it (the LiveProp above only survives polls).
        $event = $this->scenarioEvent($eventId);
        $game = $this->store->current()->withAcknowledgedEvent($eventId);
        if (null !== $event && $event->restartsClockOnAcknowledge) {
            $game = $game->withProgression($game->progression->withSpeed($game->progression->speed, new DateTimeImmutable()));
        }

        $this->commit($game);
    }

    /** Manual step: live the current day now, restarting the real-time clock. */
    #[LiveAction]
    public function step(): void
    {
        $before = $this->store->current();
        $after = $this->timeKeeper->step($before, new DateTimeImmutable());
        $this->notice = null;
        $this->noticeChantierTransitions($before, $after);
        $this->commit($after);
    }

    #[LiveAction]
    public function setSpeed(#[LiveArg] int $speed): void
    {
        $this->notice = null;
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
        $this->notice = null;
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
        $this->notice = null;

        $game = $this->timeKeeper->catchUp($this->store->current(), new DateTimeImmutable());
        $result = $this->renovations->order($game->state, $work, $financing);

        if (!$result instanceof GameState) {
            $this->commit($game); // keep the caught-up days
            $this->fail($result);

            return;
        }

        $this->commit($game->withState($result));
        $this->notice = Notice::success('Chantier commandé !');
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->selectedSlot = null;
        $this->notice = null;
        $this->acknowledgedEvents = [];
        $this->game = $this->store->reset();
        $this->view = null;
    }

    public function getGame(): GameView
    {
        return $this->view ??= $this->viewFactory->build($this->loaded()->config, $this->loaded()->state);
    }

    /** The next scenario event still awaiting its one-shot modal, if any. */
    public function getPendingScenarioEvent(): ?ScenarioEventView
    {
        foreach ($this->getGame()->occurredScenarioEvents as $event) {
            if (!in_array($event->id, $this->acknowledgedEvents, true)) {
                return $event;
            }
        }

        return null;
    }

    public function getSpeedValue(): int
    {
        return $this->loaded()->progression->speed->value;
    }

    /** @return list<string> */
    public function getZoneSlots(): array
    {
        return array_map(static fn (SceneSlot $slot): string => $slot->value, SceneSlot::cases());
    }

    /**
     * Where each panel docks: the drawer for house zones, a scene corner/edge for axis panels.
     *
     * @return array<string, string>
     */
    public function getPanelPositions(): array
    {
        $positions = [];
        foreach (SceneSlot::cases() as $slot) {
            $positions[$slot->value] = 'at-drawer';
        }
        foreach (AxisPanel::cases() as $panel) {
            $positions[$panel->value] = $panel->anchor();
        }

        return $positions;
    }

    private function fail(string $message): void
    {
        $this->notice = Notice::error($message);
    }

    private function scenarioEvent(string $eventId): ?ScenarioEventView
    {
        foreach ($this->getGame()->occurredScenarioEvents as $event) {
            if ($event->id === $eventId) {
                return $event;
            }
        }

        return null;
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
            $before = $this->store->current();
            $game = $this->timeKeeper->catchUp($before, new DateTimeImmutable());
            $this->noticeChantierTransitions($before, $game);
            $this->store->save($game);
            $this->game = $game;
        }

        return $this->game;
    }

    /**
     * Surfaces a notice when a chantier crosses a milestone between two states
     * (a day-advance, whether polled or stepped): started (the artisan arrives)
     * or posed (the work lands). Time-driven, so it lives here rather than in a
     * LiveAction. A completion is the headline — it wins over a same-tick start.
     */
    private function noticeChantierTransitions(Game $before, Game $after): void
    {
        $beforeSlugs = array_map(static fn (ScheduledWork $c): string => $c->workSlug, $before->state->scheduledWorks);
        $afterSlugs = array_map(static fn (ScheduledWork $c): string => $c->workSlug, $after->state->scheduledWorks);

        $posed = array_values(array_diff($beforeSlugs, $afterSlugs));
        if ([] !== $posed) {
            // Name the posed works: the "before" household hasn't received them
            // yet, so each still has an offer whose title reads well ("Isolation
            // des combles"). A ": name" phrasing sidesteps gender agreement.
            $names = array_values(array_filter(array_map(
                fn (string $slug): ?string => $this->catalog->tryGet($slug)?->offerFor($before->state->household)?->title,
                $posed,
            )));
            $label = [] !== $names ? implode(', ', $names) : 'travaux';
            $this->notice = Notice::success(
                1 === count($posed) ? sprintf('Chantier terminé : %s.', $label) : sprintf('Chantiers terminés : %s.', $label),
            );

            return;
        }

        foreach ($after->state->scheduledWorks as $chantier) {
            if ($before->state->currentDay < $chantier->chantierStartDay && $after->state->currentDay >= $chantier->chantierStartDay) {
                $this->notice = Notice::success('Les travaux ont commencé.');

                return;
            }
        }
    }
}
