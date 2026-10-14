<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\User;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use App\Security\Voter\OwnerVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/clients')]
final class ClientController extends AbstractController
{
    #[Route('', name: 'app_client_index', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, ClientRepository $repository): Response
    {
        $term = $request->query->getString('q');

        return $this->render('client/index.html.twig', [
            'clients' => $repository->searchQueryBuilder($user, $term)->getQuery()->getResult(),
            'term' => $term,
        ]);
    }

    #[Route('/nouveau', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $client = (new Client())->setOwner($user);
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($client);
            $em->flush();
            $this->addFlash('success', 'Client créé.');

            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
        }

        return $this->render('client/form.html.twig', ['form' => $form, 'client' => $client, 'title' => 'Nouveau client']);
    }

    #[Route('/{id}', name: 'app_client_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(OwnerVoter::VIEW, subject: 'client')]
    public function show(Client $client): Response
    {
        return $this->render('client/show.html.twig', ['client' => $client]);
    }

    #[Route('/{id}/modifier', name: 'app_client_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(OwnerVoter::EDIT, subject: 'client')]
    public function edit(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Client mis à jour.');

            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
        }

        return $this->render('client/form.html.twig', ['form' => $form, 'client' => $client, 'title' => 'Modifier le client']);
    }

    #[Route('/{id}/supprimer', name: 'app_client_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(OwnerVoter::DELETE, subject: 'client')]
    public function delete(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_client_'.$client->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$client->getQuotes()->isEmpty()) {
            $this->addFlash('error', 'Ce client a des devis : supprimez-les d\'abord.');

            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
        }

        $em->remove($client);
        $em->flush();
        $this->addFlash('success', 'Client supprimé.');

        return $this->redirectToRoute('app_client_index');
    }
}
