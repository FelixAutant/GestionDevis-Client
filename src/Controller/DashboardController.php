<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\QuoteStatus;
use App\Repository\ClientRepository;
use App\Repository\QuoteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(#[CurrentUser] User $user, ClientRepository $clients, QuoteRepository $quotes): Response
    {
        $counts = $quotes->countByStatus($user);

        return $this->render('dashboard/index.html.twig', [
            'client_count' => $clients->countFor($user),
            'counts' => $counts,
            'statuses' => QuoteStatus::cases(),
            'recent' => $quotes->findRecent($user),
        ]);
    }
}
