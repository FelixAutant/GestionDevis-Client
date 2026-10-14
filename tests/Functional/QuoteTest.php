<?php

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Enum\QuoteStatus;

final class QuoteTest extends DatabaseWebTestCase
{
    public function testListShowsOnlyMyQuotesAndTotals(): void
    {
        $this->loginAs();
        $this->client->request('GET', '/devis');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Rénovation salle de bain');
        self::assertStringContainsString('2 456,75 €', $this->normalizedText('table'));
        self::assertSelectorTextNotContains('table', 'Chauffe-eau');
    }

    public function testFilterByStatus(): void
    {
        $this->loginAs();
        $this->client->request('GET', '/devis?status=draft');

        self::assertSelectorTextContains('table', 'Remplacement vitrine');
        self::assertSelectorTextNotContains('table', 'Rénovation salle de bain');
    }

    public function testCreateQuoteComputesTotalsAndAssignsNextNumber(): void
    {
        $user = $this->loginAs();
        $client = $this->findClient('Famille Lefèvre');

        $this->submitWith('/devis/nouveau', 'Enregistrer', ['quote' => [
            'client' => (string) $client->getId(),
            'title' => 'Pose parquet',
            'lines' => [
                ['description' => 'Parquet chêne', 'quantity' => '12.5', 'unit' => 'm²', 'unitPrice' => '80.00', 'vatRate' => '2000'],
                ['description' => 'Plinthes', 'quantity' => '1', 'unit' => 'forfait', 'unitPrice' => '150.50', 'vatRate' => '1000'],
            ],
        ]]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $this->em->clear();
        $quote = $this->em->getRepository(Quote::class)->findOneBy(['title' => 'Pose parquet']);
        self::assertNotNull($quote);
        self::assertSame($user->getId(), $quote->getOwner()?->getId());
        self::assertSame(QuoteStatus::Draft, $quote->getStatus());
        self::assertStringStartsWith('DV-'.$quote->getIssueDate()?->format('Y').'-', (string) $quote->getNumber());

        // 12,5 × 80 = 1000,00 (20 %) ; 150,50 (10 %) → HT 1150,50 ; TVA 200,00 + 15,05 ; TTC 1365,55
        $totals = $quote->getTotals();
        self::assertSame(115050, $totals->totalExcludingTax);
        self::assertSame(21505, $totals->totalVat);
        self::assertSame(136555, $totals->totalIncludingTax);
        self::assertCount(2, $quote->getLines());
        $line = $quote->getLines()[0];
        self::assertInstanceOf(QuoteLine::class, $line);
        self::assertSame('Parquet chêne', $line->getDescription());
        self::assertSame(1250, $line->getQuantity());
        self::assertSame(8000, $line->getUnitPrice());
    }

    public function testQuoteNeedsAtLeastOneLine(): void
    {
        $this->loginAs();
        $client = $this->findClient('Famille Lefèvre');

        $this->submitWith('/devis/nouveau', 'Enregistrer', ['quote' => ['client' => (string) $client->getId()]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Ajoutez au moins une ligne');
    }

    public function testCannotCreateQuoteForSomeoneElsesClient(): void
    {
        $this->loginAs();
        $foreign = $this->findClient('Client de Martin');

        $this->submitWith('/devis/nouveau', 'Enregistrer', ['quote' => [
            'client' => (string) $foreign->getId(),
            'lines' => [['description' => 'x', 'quantity' => '1', 'unitPrice' => '10', 'vatRate' => '2000']],
        ]]);

        self::assertResponseStatusCodeSame(422, 'Le client d\'un autre artisan n\'est pas un choix valide.');
        $this->em->clear();
        self::assertCount(1, $this->em->getRepository(Quote::class)->findBy(['client' => $foreign]), 'Aucun devis n\'a été ajouté à ce client.');
    }

    public function testEditDraftQuote(): void
    {
        $this->loginAs();
        $draft = $this->findQuote('Remplacement vitrine');

        $this->client->request('GET', '/devis/'.$draft->getId().'/modifier');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Enregistrer', ['quote[title]' => 'Vitrine aluminium']);
        self::assertResponseRedirects('/devis/'.$draft->getId());

        $this->em->clear();
        self::assertSame('Vitrine aluminium', $this->em->getRepository(Quote::class)->find($draft->getId())?->getTitle());
    }

    public function testSentQuoteCannotBeEditedOrDeleted(): void
    {
        $this->loginAs();
        $sent = $this->findQuote('Peinture cage d\'escalier');

        $this->client->request('GET', '/devis/'.$sent->getId().'/modifier');
        self::assertResponseRedirects('/devis/'.$sent->getId());

        $crawler = $this->client->request('GET', '/devis/'.$sent->getId());
        self::assertSelectorNotExists('a[href$="/modifier"]');
        self::assertCount(0, $crawler->selectButton('Supprimer'));
    }

    public function testStatusWorkflow(): void
    {
        $this->loginAs();
        $draft = $this->findQuote('Remplacement vitrine');

        $this->changeStatus($draft, 'Marquer « Envoyé »');
        $this->changeStatus($draft, 'Marquer « Accepté »');

        $this->em->clear();
        self::assertSame(QuoteStatus::Accepted, $this->em->getRepository(Quote::class)->find($draft->getId())?->getStatus());

        $this->client->request('GET', '/devis/'.$draft->getId());
        self::assertSelectorTextContains('body', 'Statut final');
    }

    public function testInvalidStatusTransitionIsRejected(): void
    {
        $this->loginAs();
        $draft = $this->findQuote('Remplacement vitrine');

        $crawler = $this->client->request('GET', '/devis/'.$draft->getId());
        $token = $crawler->filter('form[action$="/statut"] input[name=_token]')->attr('value');
        $this->client->request('POST', '/devis/'.$draft->getId().'/statut', ['_token' => $token, 'status' => 'accepted']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash-error', 'impossible');
        $this->em->clear();
        self::assertSame(QuoteStatus::Draft, $this->em->getRepository(Quote::class)->find($draft->getId())?->getStatus());
    }

    public function testDeleteDraftQuote(): void
    {
        $this->loginAs();
        $draft = $this->findQuote('Remplacement vitrine');

        $crawler = $this->client->request('GET', '/devis/'.$draft->getId());
        $this->client->submit($crawler->selectButton('Supprimer')->form());

        self::assertResponseRedirects('/devis');
        $this->em->clear();
        self::assertNull($this->em->getRepository(Quote::class)->find($draft->getId()));
    }

    public function testCannotAccessAnotherUsersQuote(): void
    {
        $this->loginAs();
        $foreign = $this->findQuote('Chauffe-eau');
        $id = $foreign->getId();

        foreach (['/devis/%d', '/devis/%d/modifier', '/devis/%d/pdf'] as $pattern) {
            $this->client->request('GET', \sprintf($pattern, $id));
            self::assertResponseStatusCodeSame(403, $pattern);
        }

        $this->client->request('POST', \sprintf('/devis/%d/statut', $id), ['_token' => 'x', 'status' => 'sent']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', \sprintf('/devis/%d/supprimer', $id), ['_token' => 'x']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testPdfIsGenerated(): void
    {
        $this->loginAs();
        $quote = $this->findQuote('Rénovation salle de bain');

        $this->client->request('GET', '/devis/'.$quote->getId().'/pdf');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('inline', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testPdfDownloadUsesAttachmentDisposition(): void
    {
        $this->loginAs();
        $quote = $this->findQuote('Rénovation salle de bain');

        $this->client->request('GET', '/devis/'.$quote->getId().'/pdf?download=1');

        self::assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('devis-dv-', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testDashboardCountsQuotesByStatus(): void
    {
        $this->loginAs();
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(1 + \count(QuoteStatus::cases()), $crawler->filter('.stat'), 'Un bloc « clients » + un par statut.');
        self::assertStringContainsString('3', $crawler->filter('.stat')->first()->text(), 'La démo a 3 clients.');
    }

    /** Texte d'un bloc, avec toutes les variantes d'espaces (insécables, fines) ramenées à une espace simple. */
    private function normalizedText(string $selector): string
    {
        $text = $this->client->getCrawler()->filter($selector)->text();

        return (string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $text);
    }

    private function changeStatus(Quote $quote, string $button): void
    {
        $crawler = $this->client->request('GET', '/devis/'.$quote->getId());
        $this->client->submit($crawler->selectButton($button)->form());
        self::assertResponseRedirects('/devis/'.$quote->getId());
    }

    private function findClient(string $name): Client
    {
        $client = $this->em->getRepository(Client::class)->findOneBy(['name' => $name]);
        self::assertNotNull($client);

        return $client;
    }

    private function findQuote(string $title): Quote
    {
        $quote = $this->em->getRepository(Quote::class)->findOneBy(['title' => $title]);
        self::assertNotNull($quote);

        return $quote;
    }
}
