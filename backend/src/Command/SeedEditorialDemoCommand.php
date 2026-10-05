<?php

namespace App\Command;

use App\DataFixtures\EditorialDemoSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-editorial-demo', description: 'Ajoute les produits, actualités et offres de démonstration sans vider la base.')]
final class SeedEditorialDemoCommand extends Command
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
        $this->editorialDemoSeeder->seed($this->entityManager);
        $io->success('Contenus de démonstration à jour. Les demandes de contact existantes sont conservées.');

        return Command::SUCCESS;
    }
}
