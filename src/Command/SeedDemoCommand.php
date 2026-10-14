<?php

namespace App\Command;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Charge les données de démonstration, mais uniquement sur une base sans aucun utilisateur
 * et sans jamais rien purger (contrairement à doctrine:fixtures:load). Sûr à lancer à chaque démarrage.
 *
 * Avec --reset : purge puis recharge, pour remettre une démo publique à zéro. Refusé dès que la base
 * contient un compte autre que ceux des fixtures, pour ne jamais effacer de vraies données.
 */
#[AsCommand(name: 'app:demo:seed', description: 'Charge les données de démo si la base ne contient aucun utilisateur (--reset : les recharge)')]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'doctrine.fixtures.loader')]
        private readonly SymfonyFixturesLoader $loader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Purge et recharge les données de démo (refusé si la base contient d\'autres comptes)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('reset')) {
            $otherAccounts = (int) $this->em->createQuery('SELECT COUNT(u.id) FROM '.User::class.' u WHERE u.email NOT IN (:demo)')
                ->setParameter('demo', [AppFixtures::DEMO_EMAIL, AppFixtures::OTHER_EMAIL])
                ->getSingleScalarResult();
            if ($otherAccounts > 0) {
                $io->error('La base contient des comptes hors démo : remise à zéro refusée.');

                return Command::FAILURE;
            }

            (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($this->loader->getFixtures());
            $io->success('Données de démo remises à zéro.');

            return Command::SUCCESS;
        }

        if ($this->em->getRepository(User::class)->count([]) > 0) {
            $io->note('La base contient déjà des utilisateurs : rien à faire.');

            return Command::SUCCESS;
        }

        (new ORMExecutor($this->em))->execute($this->loader->getFixtures(), true); // append : aucune purge
        $io->success('Données de démo chargées.');

        return Command::SUCCESS;
    }
}
