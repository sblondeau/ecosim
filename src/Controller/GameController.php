<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\GameStore;
use App\Application\GameViewFactory;
use App\Domain\Simulation\SimulationEngine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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

    #[Route('/jour-suivant', name: 'app_game_advance', methods: ['POST'])]
    public function advance(Request $request): Response
    {
        $this->denyUnlessValidCsrfToken($request);

        $game = $this->store->current();
        $this->store->save($game->withState($this->engine->advance($game->config, $game->state)));

        return $this->redirectToRoute('app_game');
    }

    #[Route('/nouvelle-partie', name: 'app_game_reset', methods: ['POST'])]
    public function reset(Request $request): Response
    {
        $this->denyUnlessValidCsrfToken($request);

        $this->store->reset();

        return $this->redirectToRoute('app_game');
    }

    private function denyUnlessValidCsrfToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('game', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
