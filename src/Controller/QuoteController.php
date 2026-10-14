<?php

namespace App\Controller;

use App\Entity\Quote;
use App\Entity\User;
use App\Enum\QuoteStatus;
use App\Form\QuoteType;
use App\Repository\ClientRepository;
use App\Repository\QuoteRepository;
use App\Security\Voter\OwnerVoter;
use App\Service\QuoteNumberGenerator;
use App\Service\QuotePdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/devis')]
final class QuoteController extends AbstractController
{
    #[Route('', name: 'app_quote_index', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, QuoteRepository $repository): Response
    {
        $status = QuoteStatus::tryFrom($request->query->getString('status'));
        $term = $request->query->getString('q');

        return $this->render('quote/index.html.twig', [
            'quotes' => $repository->searchQueryBuilder($user, $status, $term)->getQuery()->getResult(),
            'status' => $status,
            'term' => $term,
            'statuses' => QuoteStatus::cases(),
        ]);
    }

    #[Route('/nouveau', name: 'app_quote_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        #[CurrentUser] User $user,
        EntityManagerInterface $em,
        ClientRepository $clients,
        QuoteNumberGenerator $numbers,
    ): Response {
        $quote = (new Quote())->setOwner($user);

        // Pré-sélection du client depuis sa fiche (?client=ID), uniquement s'il appartient à l'utilisateur.
        $preselected = $clients->find($request->query->getInt('client'));
        if (null !== $preselected && $this->isGranted(OwnerVoter::VIEW, $preselected)) {
            $quote->setClient($preselected);
        }

        $form = $this->createForm(QuoteType::class, $quote, ['owner' => $user]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $issueDate = $quote->getIssueDate() ?? throw new \LogicException('Date du devis garantie par la validation.');
            $quote->setNumber($numbers->next($user, $issueDate));
            $em->persist($quote);
            $em->flush();
            $this->addFlash('success', \sprintf('Devis %s créé.', $quote->getNumber()));

            return $this->redirectToRoute('app_quote_show', ['id' => $quote->getId()]);
        }

        return $this->render('quote/form.html.twig', ['form' => $form, 'quote' => $quote, 'title' => 'Nouveau devis']);
    }

    #[Route('/{id}', name: 'app_quote_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(OwnerVoter::VIEW, subject: 'quote')]
    public function show(Quote $quote): Response
    {
        return $this->render('quote/show.html.twig', ['quote' => $quote, 'totals' => $quote->getTotals()]);
    }

    #[Route('/{id}/modifier', name: 'app_quote_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(OwnerVoter::EDIT, subject: 'quote')]
    public function edit(Request $request, Quote $quote, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        if (!$quote->isEditable()) {
            $this->addFlash('error', 'Seul un devis en brouillon peut être modifié.');

            return $this->redirectToRoute('app_quote_show', ['id' => $quote->getId()]);
        }

        $form = $this->createForm(QuoteType::class, $quote, ['owner' => $user]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($quote->getLines() as $position => $line) {
                $line->setPosition($position);
            }
            $quote->touch();
            $em->flush();
            $this->addFlash('success', 'Devis mis à jour.');

            return $this->redirectToRoute('app_quote_show', ['id' => $quote->getId()]);
        }

        return $this->render('quote/form.html.twig', ['form' => $form, 'quote' => $quote, 'title' => 'Modifier le devis '.$quote->getNumber()]);
    }

    #[Route('/{id}/statut', name: 'app_quote_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(OwnerVoter::EDIT, subject: 'quote')]
    public function changeStatus(Request $request, Quote $quote, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('status_quote_'.$quote->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $target = QuoteStatus::tryFrom($request->request->getString('status'));
        if (null === $target || !$quote->getStatus()->canTransitionTo($target)) {
            $this->addFlash('error', 'Changement de statut impossible.');

            return $this->redirectToRoute('app_quote_show', ['id' => $quote->getId()]);
        }

        $quote->setStatus($target)->touch();
        $em->flush();
        $this->addFlash('success', \sprintf('Devis passé en « %s ».', $target->label()));

        return $this->redirectToRoute('app_quote_show', ['id' => $quote->getId()]);
    }

    #[Route('/{id}/pdf', name: 'app_quote_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(OwnerVoter::VIEW, subject: 'quote')]
    public function pdf(Request $request, Quote $quote, QuotePdfGenerator $generator): Response
    {
        $disposition = $request->query->getBoolean('download') ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE;

        $response = new Response($generator->render($quote), Response::HTTP_OK, ['Content-Type' => 'application/pdf']);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition($disposition, $generator->filename($quote)));

        return $response;
    }

    #[Route('/{id}/supprimer', name: 'app_quote_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(OwnerVoter::DELETE, subject: 'quote')]
    public function delete(Request $request, Quote $quote, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_quote_'.$quote->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$quote->isEditable()) {
            $this->addFlash('error', 'Seul un devis en brouillon peut être supprimé.');

            return $this->redirectToRoute('app_quote_show', ['id' => $quote->getId()]);
        }

        $em->remove($quote);
        $em->flush();
        $this->addFlash('success', 'Devis supprimé.');

        return $this->redirectToRoute('app_quote_index');
    }
}
