<?php

namespace App\Controller;

use App\Entity\Quote;
use App\Entity\User;
use App\Enum\QuoteStatus;
use App\Repository\ClientRepository;
use App\Repository\QuoteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(#[CurrentUser] User $user, ClientRepository $clients, QuoteRepository $quotes): Response
    {
        $counts = $quotes->countByStatus($user);
        $accepted = $counts[QuoteStatus::Accepted->value] ?? 0;
        $decided = $accepted + ($counts[QuoteStatus::Rejected->value] ?? 0);

        return $this->render('dashboard/index.html.twig', [
            'client_count' => $clients->countFor($user),
            'counts' => $counts,
            'quote_count' => array_sum($counts),
            'statuses' => QuoteStatus::cases(),
            'recent' => $quotes->findRecent($user),
            // Montants TTC en centimes, calculés comme sur chaque devis (TVA par taux).
            'signed_total' => $this->sumIncludingTax($quotes, $user, QuoteStatus::Accepted),
            'pending_total' => $this->sumIncludingTax($quotes, $user, QuoteStatus::Sent),
            // Part des devis acceptés parmi ceux qui ont reçu une réponse ; null tant qu'aucun n'en a.
            'acceptance_rate' => $decided > 0 ? (int) round(100 * $accepted / $decided) : null,
        ]);
    }

    private function sumIncludingTax(QuoteRepository $quotes, User $user, QuoteStatus $status): int
    {
        /** @var list<Quote> $found */
        $found = $quotes->searchQueryBuilder($user, $status)->getQuery()->getResult();

        return array_sum(array_map(static fn (Quote $quote): int => $quote->getTotals()->totalIncludingTax, $found));
    }
}
