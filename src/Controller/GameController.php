<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\GameStore;
use App\Application\RenovationHandler;
use App\Application\TimeKeeper;
use App\Domain\Finance\Renovation;
use App\Domain\Simulation\GameState;
use App\Domain\Time\TickSpeed;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * The Phase 0-1 dashboard: one household, real-time days.
 *
 * The page is a thin shell around the {@see \App\Twig\Components\GameDashboard}
 * live component (which polls and catches the game up with the clock); the
 * POST routes handle the player's actions. Every mutation first catches up via
 * {@see TimeKeeper} so game time only flows through one door.
 */
final class GameController extends AbstractController
{
    public function __construct(
        private readonly GameStore $store,
        private readonly TimeKeeper $timeKeeper,
        private readonly RenovationHandler $renovations,
    ) {
    }

    #[Route('/', name: 'app_game', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('game/dashboard.html.twig');
    }

    #[IsCsrfTokenValid('game', tokenKey: '_token')]
    #[Route('/jour-suivant', name: 'app_game_advance', methods: ['POST'])]
    public function advance(): Response
    {
        $this->store->save($this->timeKeeper->step($this->store->current(), new DateTimeImmutable()));

        return $this->redirectToRoute('app_game');
    }

    #[IsCsrfTokenValid('game', tokenKey: '_token')]
    #[Route('/vitesse', name: 'app_game_speed', methods: ['POST'])]
    public function speed(Request $request): Response
    {
        $speed = TickSpeed::tryFrom($request->getPayload()->getInt('speed'));

        if (null !== $speed) {
            $now = new DateTimeImmutable();
            $game = $this->timeKeeper->catchUp($this->store->current(), $now);
            $this->store->save($game->withProgression($game->progression->withSpeed($speed, $now)));
        }

        return $this->redirectToRoute('app_game');
    }

    #[IsCsrfTokenValid('game', tokenKey: '_token')]
    #[Route('/travaux', name: 'app_game_renovate', methods: ['POST'])]
    public function renovate(Request $request): Response
    {
        $work = Renovation::tryFrom($request->getPayload()->getString('work'));
        if (null === $work) {
            $this->addFlash('error', 'Travaux inconnus.');

            return $this->redirectToRoute('app_game');
        }

        $game = $this->timeKeeper->catchUp($this->store->current(), new DateTimeImmutable());
        $result = $this->renovations->order(
            $game->state,
            $work,
            $request->getPayload()->getString('financing'),
        );

        if (!$result instanceof GameState) {
            $this->store->save($game);
            $this->addFlash('error', $result);

            return $this->redirectToRoute('app_game');
        }

        $this->store->save($game->withState($result));
        $this->addFlash('success', 'Travaux réalisés !');

        return $this->redirectToRoute('app_game');
    }

    #[IsCsrfTokenValid('game', tokenKey: '_token')]
    #[Route('/nouvelle-partie', name: 'app_game_reset', methods: ['POST'])]
    public function reset(): Response
    {
        $this->store->reset();

        return $this->redirectToRoute('app_game');
    }
}
