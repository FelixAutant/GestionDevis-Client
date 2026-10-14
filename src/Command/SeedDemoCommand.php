<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Charge les données de démonstration, mais uniquement sur une base sans aucun utilisateur
 * et sans jamais rien purger (contrairement à doctrine:fixtures:load). Sûr à lancer à chaque démarrage.
 */
#[AsCommand(name: 'app:demo:seed', description: 'Charge les données de démo si la base ne contient aucun utilisateur')]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'doctrine.fixtures.loader')]
        private readonly SymfonyFixturesLoader $loader,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->em->getRepository(User::class)->count([]) > 0) {
            $io->note('La base contient déjà des utilisateurs : rien à faire.');

            return Command::SUCCESS;
        }

        (new ORMExecutor($this->em))->execute($this->loader->getFixtures(), true); // append : aucune purge
        $io->success('Données de démo chargées.');

        return Command::SUCCESS;
    }
}
