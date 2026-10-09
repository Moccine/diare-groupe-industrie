<?php

namespace App\Command;

use App\Repository\JobApplicationRepository;
use App\Service\JobApplicationCvStorage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:job-applications:purge',
    description: 'Liste ou supprime les candidatures dont la date de conservation est dépassée. Ne s’exécute pas tout seul.',
)]
final class PurgeJobApplicationsCommand extends Command
{
    public function __construct(
        private readonly JobApplicationRepository $applications,
        private readonly JobApplicationCvStorage $storage,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('execute', null, InputOption::VALUE_NONE, 'Supprime réellement les dossiers arrivés à échéance.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $due = $this->applications->findReadyForPurge(new \DateTimeImmutable());
        $execute = (bool) $input->getOption('execute');

        if ($due === []) {
            $io->success('Aucune candidature à purger.');

            return Command::SUCCESS;
        }

        if (!$execute) {
            $io->warning(sprintf('%d candidature(s) arriveraient à échéance. Relancer avec --execute après validation de la durée de conservation.', count($due)));

            return Command::SUCCESS;
        }

        $removed = 0;
        foreach ($due as $application) {
            $filename = $application->getCvStoredFilename();
            $id = $application->getId();
            $this->storage->delete($filename);
            if ($filename !== '' && $this->storage->exists($filename)) {
                $this->logger->error('Purge interrompue pour une candidature : le CV est toujours présent.', [
                    'applicationId' => $id,
                ]);
                $io->error(sprintf('Candidature %s conservée : le CV n’a pas pu être supprimé.', (string) $id));

                continue;
            }

            $this->entityManager->remove($application);
            $this->entityManager->flush();
            ++$removed;
        }

        $io->success(sprintf('%d candidature(s) supprimée(s), avec leur CV.', $removed));

        return Command::SUCCESS;
    }
}
