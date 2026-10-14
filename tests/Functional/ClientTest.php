<?php

namespace App\Tests\Functional;

use App\Entity\Client;

final class ClientTest extends DatabaseWebTestCase
{
    public function testListShowsOnlyMyClients(): void
    {
        $this->loginAs();
        $this->client->request('GET', '/clients');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Famille Lefèvre');
        self::assertSelectorTextNotContains('table', 'Client de Martin');
    }

    public function testSearchFiltersByName(): void
    {
        $this->loginAs();
        $this->client->request('GET', '/clients?q=cèdres');

        self::assertSelectorTextContains('table', 'SCI Les Cèdres');
        self::assertSelectorTextNotContains('table', 'Famille Lefèvre');
    }

    public function testCreateClient(): void
    {
        $user = $this->loginAs();
        $this->client->request('GET', '/clients/nouveau');
        $this->client->submitForm('Enregistrer', [
            'client[name]' => 'Mairie de Bidart',
            'client[email]' => 'mairie@example.com',
            'client[city]' => 'Bidart',
        ]);

        self::assertResponseRedirects();
        $created = $this->em->getRepository(Client::class)->findOneBy(['name' => 'Mairie de Bidart']);
        self::assertNotNull($created);
        self::assertSame($user->getId(), $created->getOwner()?->getId(), 'Le client appartient à l\'utilisateur connecté.');
    }

    public function testNameIsRequired(): void
    {
        $this->loginAs();
        $this->client->request('GET', '/clients/nouveau');
        $this->client->submitForm('Enregistrer', ['client[name]' => '']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testEditClient(): void
    {
        $this->loginAs();
        $existing = $this->findClient('SCI Les Cèdres');
        $this->client->request('GET', '/clients/'.$existing->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['client[city]' => 'Saint-Jean-de-Luz']);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertSame('Saint-Jean-de-Luz', $this->findClient('SCI Les Cèdres')->getCity());
    }

    public function testCannotAccessAnotherUsersClient(): void
    {
        $this->loginAs();
        $foreign = $this->findClient('Client de Martin');

        foreach (['/clients/%d', '/clients/%d/modifier'] as $pattern) {
            $this->client->request('GET', \sprintf($pattern, $foreign->getId()));
            self::assertResponseStatusCodeSame(403);
        }

        $this->client->request('POST', \sprintf('/clients/%d/supprimer', $foreign->getId()), ['_token' => 'x']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteClientWithoutQuotes(): void
    {
        $user = $this->loginAs();
        $lonely = (new Client())->setOwner($user)->setName('Sans devis');
        $this->em->persist($lonely);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/clients/'.$lonely->getId());
        $this->client->submit($crawler->selectButton('Supprimer')->form());

        self::assertResponseRedirects('/clients');
        $this->em->clear();
        self::assertNull($this->em->getRepository(Client::class)->findOneBy(['name' => 'Sans devis']));
    }

    public function testCannotDeleteClientWithQuotes(): void
    {
        $this->loginAs();
        $withQuotes = $this->findClient('Famille Lefèvre');

        $crawler = $this->client->request('GET', '/clients/'.$withQuotes->getId());
        $this->client->submit($crawler->selectButton('Supprimer')->form());
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash-error', 'supprimez-les');
        $this->em->clear();
        $this->findClient('Famille Lefèvre'); // échoue si le client a été supprimé
    }

    private function findClient(string $name): Client
    {
        $client = $this->em->getRepository(Client::class)->findOneBy(['name' => $name]);
        self::assertNotNull($client);

        return $client;
    }
}
