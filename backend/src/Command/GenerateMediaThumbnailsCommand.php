<?php

namespace App\Command;

use App\Repository\MediaRepository;
use App\Service\MediaThumbnailGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:media:generate-thumbnails',
    description: 'Génère les miniatures manquantes de la bibliothèque d’images.',
)]
final class GenerateMediaThumbnailsCommand extends Command
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly MediaThumbnailGenerator $thumbnails,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Régénère aussi les miniatures déjà présentes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $created = 0;
        $skipped = 0;
        $missing = 0;
        $failed = 0;

        foreach ($this->mediaRepository->findBy([], ['id' => 'ASC']) as $media) {
            $fileName = $media->getFileName();
            if ($this->thumbnails->sourcePath($media) === null) {
                $io->writeln('SKIP / missing file '.($fileName !== '' ? $fileName : '#'.$media->getId()));
                ++$missing;
                continue;
            }

            if (!$force && $this->thumbnails->thumbnailExists($media)) {
                ++$skipped;
                continue;
            }

            if ($this->thumbnails->generate($media)) {
                ++$created;
                continue;
            }

            ++$failed;
            $io->writeln('FAIL / '.$fileName);
        }

        $io->success(sprintf(
            'Miniatures : %d créée(s), %d déjà présente(s), %d fichier(s) absent(s), %d échec(s).',
            $created,
            $skipped,
            $missing,
            $failed,
        ));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
