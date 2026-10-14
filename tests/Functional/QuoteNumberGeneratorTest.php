<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Client;
use App\Entity\Quote;
use App\Entity\User;
use App\Service\QuoteNumberGenerator;

final class QuoteNumberGeneratorTest extends DatabaseWebTestCase
{
    public function testSequenceStartsAtOneEachYearAndIsPerUser(): void
    {
        $demo = $this->user(AppFixtures::DEMO_EMAIL);
        $other = $this->user(AppFixtures::OTHER_EMAIL);
        $generator = static::getContainer()->get(QuoteNumberGenerator::class);
        $day = new \DateTimeImmutable('2031-05-01');

        // Aucune fixture en 2031 : on repart de 1.
        self::assertSame('DV-2031-0001', $generator->next($demo, $day));

        $this->persistQuote($demo, 'DV-2031-0001', $day);
        $this->persistQuote($demo, 'DV-2031-0002', $day);

        self::assertSame('DV-2031-0003', $generator->next($demo, $day));
        self::assertSame('DV-2031-0001', $generator->next($other, $day), 'La séquence est propre à chaque artisan.');
        self::assertSame('DV-2032-0001', $generator->next($demo, new \DateTimeImmutable('2032-01-02')), 'Nouvelle année, nouvelle séquence.');
    }

    private function user(string $email): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        return $user;
    }

    private function persistQuote(User $owner, string $number, \DateTimeImmutable $date): void
    {
        $client = $this->em->getRepository(Client::class)->findOneBy(['owner' => $owner]);
        self::assertNotNull($client);

        $quote = (new Quote())->setOwner($owner)->setClient($client)->setNumber($number)->setIssueDate($date)->setValidUntil($date->modify('+30 days'));
        $this->em->persist($quote);
        $this->em->flush();
    }
}
