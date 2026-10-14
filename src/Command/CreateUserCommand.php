<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:user:create', description: 'Crée un compte artisan (il n\'y a pas d\'inscription publique)')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse e-mail (identifiant de connexion)')
            ->addArgument('company', InputArgument::REQUIRED, 'Nom de l\'entreprise')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Mot de passe (sinon demandé en saisie masquée)')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Donne le rôle ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $password = $input->getOption('password') ?? $io->askHidden('Mot de passe (8 caractères minimum)');
        if (!\is_string($password) || mb_strlen($password) < 8) {
            $io->error('Le mot de passe doit faire au moins 8 caractères.');

            return Command::FAILURE;
        }

        $user = (new User())
            ->setEmail($input->getArgument('email'))
            ->setCompanyName($input->getArgument('company'))
            ->setRoles($input->getOption('admin') ? ['ROLE_ADMIN'] : []);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $errors = $this->validator->validate($user);
        if (\count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error->getMessage());
            }

            return Command::FAILURE;
        }

        $this->em->persist($user);
        $this->em->flush();
        $io->success(\sprintf('Compte %s créé.', $user->getEmail()));

        return Command::SUCCESS;
    }
}
