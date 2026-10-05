<?php

namespace App\Command;

use App\Service\AdminUserProvisioner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:create-admin', description: 'Crée ou met à jour le compte administrateur défini dans l’environnement.')]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly AdminUserProvisioner $adminUserProvisioner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $user = $this->adminUserProvisioner->provision();
        $io->success(sprintf('Compte administrateur prêt : %s', $user->getEmail()));

        return Command::SUCCESS;
    }
}
