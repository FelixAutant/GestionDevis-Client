<?php

namespace App\Controller;

use App\DataFixtures\AppFixtures;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'bool:DEMO_MODE')]
        private readonly bool $demoMode,
    ) {
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'demo_mode' => $this->demoMode,
        ]);
    }

    /**
     * Connexion en un clic au compte de démo, seulement quand DEMO_MODE est actif.
     */
    #[Route('/demo', name: 'app_demo_login', methods: ['POST'])]
    public function demoLogin(Request $request, UserRepository $users, Security $security): Response
    {
        $demo = $this->demoMode ? $users->findOneBy(['email' => AppFixtures::DEMO_EMAIL]) : null;
        if (null === $demo) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('demo_login', $request->getPayload()->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $security->login($demo, 'form_login', 'main');

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Intercepté par le pare-feu.');
    }
}
