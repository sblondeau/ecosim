<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\GameStore;
use App\Application\GameViewFactory;
use App\Application\RenovationHandler;
use App\Domain\Finance\Renovation;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\SimulationEngine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * The Phase 0-1 dashboard: one household, one day at a time.
 *
 * Server-rendered, session-backed and JavaScript-free — the playable vertical
 * slice. The controller only ever hands a {@see \App\Application\GameView} to
 * the template, keeping the presentation decoupled from the simulation.
 */
final class GameController extends AbstractController
{
    public function __construct(
        private readonly GameStore $store,
        private readonly GameViewFactory $viewFactory,
        private readonly SimulationEngine $engine,
        private readonly RenovationHandler $renovations,
    ) {
    }

    #[Route('/', name: 'app_game', methods: ['GET'])]
    public function dashboard(): Response
    {
        $game = $this->store->current();

        return $this->render('game/dashboard.html.twig', [
            'game' => $this->viewFactory->build($game->config, $game->state),
        ]);
    }

    #[IsCsrfTokenValid('game', tokenKey: '_token')]
    #[Route('/jour-suivant', name: 'app_game_advance', methods: ['POST'])]
    public function advance(): Response
    {
        $game = $this->store->current();
        $this->store->save($game->withState($this->engine->advance($game->config, $game->state)));

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

        $game = $this->store->current();
        $result = $this->renovations->order(
            $game->state,
            $work,
            $request->getPayload()->getString('financing'),
        );

        if (!$result instanceof GameState) {
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
