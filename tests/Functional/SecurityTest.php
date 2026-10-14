<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use PHPUnit\Framework\Attributes\DataProvider;

final class SecurityTest extends DatabaseWebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function protectedUrls(): iterable
    {
        foreach (['/', '/clients', '/clients/nouveau', '/devis', '/devis/nouveau', '/clients/1', '/devis/1', '/devis/1/pdf'] as $url) {
            yield $url => [$url];
        }
    }

    #[DataProvider('protectedUrls')]
    public function testAnonymousUsersAreRedirectedToLogin(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseRedirects('/login');
    }

    public function testLoginWithValidCredentials(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['_username' => AppFixtures::DEMO_EMAIL, '_password' => AppFixtures::DEMO_PASSWORD]);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Tableau de bord');
    }

    public function testLoginWithWrongPasswordShowsAnError(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['_username' => AppFixtures::DEMO_EMAIL, '_password' => 'mauvais']);
        $this->client->followRedirect();

        self::assertSelectorExists('.flash-error');
    }

    public function testLogoutEndsTheSession(): void
    {
        $this->loginAs();
        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/clients');

        self::assertResponseRedirects('/login');
    }

    public function testDemoButtonLogsInAsTheDemoUser(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Essayer la démo sans compte');

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Tableau de bord');
    }

    public function testDemoLoginRequiresAValidCsrfToken(): void
    {
        $this->client->request('POST', '/demo', ['_csrf_token' => 'invalide']);
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testDemoLoginDoesNotWorkWithGet(): void
    {
        $this->client->request('GET', '/demo');
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }
}
