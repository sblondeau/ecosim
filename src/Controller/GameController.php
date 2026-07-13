<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The Phase 0-1 dashboard page — a thin shell around the
 * {@see \App\Twig\Components\GameDashboard} live component, which owns the
 * scene, the panels and every player action (LiveActions). Nothing else lives
 * here: the game is entirely driven through the component.
 */
final class GameController extends AbstractController
{
    #[Route('/', name: 'app_game', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('game/dashboard.html.twig');
    }
}
