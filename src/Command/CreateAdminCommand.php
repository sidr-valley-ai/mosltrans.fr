<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an administrator account',
)]
class CreateAdminCommand extends Command
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $emailQuestion = new Question('Email de l’administrateur : ');
        $emailQuestion->setValidator($this->validateEmail(...));
        $email = $helper->ask($input, $output, $emailQuestion);

        if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
            $io->error(sprintf('Un compte existe déjà avec l’email "%s".', $email));

            return Command::FAILURE;
        }

        $passwordQuestion = new Question('Mot de passe : ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        $passwordQuestion->setValidator($this->validatePassword(...));
        $password = $helper->ask($input, $output, $passwordQuestion);

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $password)
        );

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Administrateur %s créé avec succès.',
            $email
        ));

        return Command::SUCCESS;
    }

    private function validateEmail(?string $email): string
    {
        if ($email === null || trim($email) === '') {
            throw new InvalidArgumentException('L’email ne peut pas être vide.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(sprintf('"%s" n’est pas une adresse email valide.', $email));
        }

        return $email;
    }

    private function validatePassword(?string $password): string
    {
        if ($password === null || $password === '') {
            throw new InvalidArgumentException('Le mot de passe ne peut pas être vide.');
        }

        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Le mot de passe doit contenir au moins %d caractères.',
                self::MIN_PASSWORD_LENGTH
            ));
        }

        return $password;
    }
}
