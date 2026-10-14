<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Recharge les fixtures avant chaque test : base de test identique à chaque fois.
 */
abstract class DatabaseWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);

        $executor = new ORMExecutor($this->em, new ORMPurger($this->em));
        $executor->execute([new AppFixtures($container->get(UserPasswordHasherInterface::class))]);
        $this->em->clear();
    }

    protected function loginAs(string $email = AppFixtures::DEMO_EMAIL): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);
        $this->client->loginUser($user);

        return $user;
    }

    /**
     * Soumet le formulaire contenant le bouton donné, en y ajoutant des valeurs
     * (utile pour les collections ajoutées en JavaScript côté navigateur).
     *
     * @param array<string, mixed> $extra valeurs imbriquées fusionnées dans la requête
     */
    protected function submitWith(string $uri, string $button, array $extra = []): void
    {
        $crawler = $this->client->request('GET', $uri);
        $form = $crawler->selectButton($button)->form();
        $values = array_replace_recursive($form->getPhpValues(), $extra);
        $this->client->request($form->getMethod(), $form->getUri(), $values);
    }
}
