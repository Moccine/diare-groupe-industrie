<?php

namespace App\Command;

use App\DataFixtures\EditorialDemoSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:assign-default-page-banners',
    description: 'Associe les images de bannière par défaut aux pages intérieures qui n’en ont pas.',
)]
final class AssignDefaultPageBannersCommand extends Command
{
    public function __construct(
        private readonly EditorialDemoSeeder $editorialDemoSeeder,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->editorialDemoSeeder->assignDefaultPageBanners($this->entityManager);
        $this->entityManager->flush();
        $io->success('Bannières par défaut associées. Une image déjà choisie dans le back-office est conservée.');

        return Command::SUCCESS;
    }
}
