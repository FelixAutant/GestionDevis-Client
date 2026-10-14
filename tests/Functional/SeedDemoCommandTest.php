<?php

namespace App\Tests\Functional;

use App\Entity\Quote;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedDemoCommandTest extends DatabaseWebTestCase
{
    public function testDoesNothingWhenUsersAlreadyExist(): void
    {
        $users = $this->em->getRepository(User::class)->count([]);
        $quotes = $this->em->getRepository(Quote::class)->count([]);

        $tester = $this->tester();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('rien à faire', $tester->getDisplay());
        self::assertSame($users, $this->em->getRepository(User::class)->count([]));
        self::assertSame($quotes, $this->em->getRepository(Quote::class)->count([]));
    }

    public function testSeedsAnEmptyDatabaseWithoutDuplicating(): void
    {
        $this->em->createQuery('DELETE FROM '.Quote::class)->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Client')->execute();
        $this->em->createQuery('DELETE FROM '.User::class)->execute();
        $this->em->clear();

        $tester = $this->tester();
        $tester->execute([]);
        self::assertStringContainsString('chargées', $tester->getDisplay());
        $count = $this->em->getRepository(User::class)->count([]);
        self::assertSame(2, $count);

        $tester->execute([]); // deuxième lancement : idempotent
        self::assertSame($count, $this->em->getRepository(User::class)->count([]));
    }

    private function tester(): CommandTester
    {
        $application = new Application(static::$kernel);

        return new CommandTester($application->find('app:demo:seed'));
    }
}
