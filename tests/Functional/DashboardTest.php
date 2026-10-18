<?php

namespace App\Tests\Functional;

final class DashboardTest extends DatabaseWebTestCase
{
    public function testShowsSignedAndPendingAmountsAndAcceptanceRate(): void
    {
        $this->loginAs();
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $kpis = $crawler->filter('.kpi .kpi-value')->each(static fn ($node): string => $node->text());
        // Fixtures : un devis accepté (2 456,75 € TTC), un envoyé (1 846,20 €), un refusé.
        self::assertStringContainsString('456,75', $kpis[0]);
        self::assertStringContainsString('846,20', $kpis[1]);
        self::assertSame('50 %', $kpis[2]);
        self::assertSame('3', $kpis[3]);
    }

    public function testDoesNotShowAnotherUsersAmounts(): void
    {
        $this->loginAs(\App\DataFixtures\AppFixtures::OTHER_EMAIL);
        $crawler = $this->client->request('GET', '/');

        $kpis = $crawler->filter('.kpi .kpi-value')->each(static fn ($node): string => $node->text());
        self::assertStringContainsString('0,00', $kpis[0]);
        self::assertSame('—', $kpis[2]);
    }
}
