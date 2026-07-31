<?php

declare(strict_types=1);

namespace App\Command\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFileVersionRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Makes deletions in Band Space actually reach the object storage (#748).
 *
 * Deleting a file only sets archiveDatetime, and deleting a space only sets deletionScheduledDatetime;
 * neither removes anything from storage. Worse, the storage objects cannot be reclaimed by the bucket
 * lifecycle rule either, because that rule expires *non-current* versions and only ever fires on a real
 * DELETE. Without this command the bucket grows forever.
 *
 * Storage is always deleted before the rows: if storage fails, the rows survive and the next run retries.
 * The reverse order would lose the only pointer to the object and orphan it permanently.
 */
#[AsCommand(
    name: 'app:band-space:purge',
    description: 'Delete archived Band Space files and spaces past their grace period, from the database and from object storage',
)]
class PurgeBandSpaceStorageCommand extends Command
{
    private const int DEFAULT_DAYS = 30;

    /**
     * Mirrors the directory namer of the band_space_file mapping (config/packages/vich_uploader.yaml):
     * every object of a space lives under this prefix, so a space can be wiped in one batched call.
     */
    private const string STORAGE_PREFIX = 'band_space_files';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BandSpaceRepository $bandSpaceRepository,
        private readonly BandSpaceFileRepository $bandSpaceFileRepository,
        private readonly BandSpaceFileVersionRepository $bandSpaceFileVersionRepository,
        private readonly FilesystemOperator $musicallFilesystem,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Delete files archived more than this many days ago',
                (string) self::DEFAULT_DAYS,
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be deleted without deleting anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        if ($days < 0) {
            $output->writeln('<error>Option --days cannot be negative</error>');

            return Command::FAILURE;
        }

        $isDryRun = (bool) $input->getOption('dry-run');
        if ($isDryRun) {
            $output->writeln('<comment>Dry run: nothing will be deleted</comment>');
        }

        $now = new DateTimeImmutable();
        $cutoff = $now->modify(sprintf('-%d days', $days));

        $failures = $this->purgeArchivedFiles($output, $cutoff, $isDryRun);
        $failures += $this->purgeScheduledBandSpaces($output, $now, $isDryRun);

        return $failures === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Versions are removed one by one through the ORM on purpose: BandSpaceFile has no association to
     * them, so a plain remove() of the file would drop the rows by FK cascade without VichUploader ever
     * running, leaving every object behind in the bucket.
     *
     * @return int the number of files that could not be purged
     */
    private function purgeArchivedFiles(OutputInterface $output, DateTimeImmutable $cutoff, bool $isDryRun): int
    {
        $files = $this->bandSpaceFileRepository->findArchivedOlderThan($cutoff);
        if ($files === []) {
            $output->writeln(sprintf('<info>No archived file older than %s</info>', $cutoff->format('Y-m-d H:i:s')));

            return 0;
        }

        $purged = 0;
        $failures = 0;

        foreach ($files as $file) {
            if ($isDryRun) {
                $output->writeln(sprintf('  would delete file %s (%s)', (string) $file->id, $file->originalName));
                ++$purged;

                continue;
            }

            try {
                $this->purgeFile($file);
                ++$purged;
            } catch (\Throwable $e) {
                ++$failures;
                $this->logger->error('Failed to purge archived band space file', [
                    'band_space_file_id' => (string) $file->id,
                    'exception' => $e,
                ]);
                $output->writeln(sprintf('<error>Failed to purge file %s: %s</error>', (string) $file->id, $e->getMessage()));
            }
        }

        $output->writeln(sprintf(
            '<info>%s %d archived file(s) older than %s</info>',
            $isDryRun ? 'Would delete' : 'Deleted',
            $purged,
            $cutoff->format('Y-m-d H:i:s'),
        ));

        return $failures;
    }

    private function purgeFile(BandSpaceFile $file): void
    {
        // Detach the pointer first. The database would cope on its own (the FK is ON DELETE SET NULL),
        // but this keeps Doctrine's in-memory graph honest: no managed entity is left pointing at a row
        // that has just been removed.
        $file->currentVersion = null;
        $this->entityManager->flush();

        // No explicit storage call here, unlike purgeBandSpace(): VichUploader listens on the removal of
        // an uploadable entity and deletes the object itself (delete_on_remove, on by default). That is
        // the whole reason the versions go through the ORM one by one instead of dying by FK cascade.
        foreach ($this->bandSpaceFileVersionRepository->findByFileNewestFirst($file) as $version) {
            $this->entityManager->remove($version);
        }
        $this->entityManager->flush();

        $this->entityManager->remove($file);
        $this->entityManager->flush();
    }

    /**
     * @return int the number of spaces that could not be purged
     */
    private function purgeScheduledBandSpaces(OutputInterface $output, DateTimeImmutable $now, bool $isDryRun): int
    {
        $bandSpaces = $this->bandSpaceRepository->findScheduledForDeletion($now);
        if ($bandSpaces === []) {
            $output->writeln('<info>No band space past its deletion grace period</info>');

            return 0;
        }

        $purged = 0;
        $failures = 0;

        foreach ($bandSpaces as $bandSpace) {
            if ($isDryRun) {
                $output->writeln(sprintf('  would delete band space %s (%s)', (string) $bandSpace->id, $bandSpace->name));
                ++$purged;

                continue;
            }

            try {
                $this->purgeBandSpace($bandSpace);
                ++$purged;
            } catch (\Throwable $e) {
                ++$failures;
                $this->logger->error('Failed to purge band space', [
                    'band_space_id' => (string) $bandSpace->id,
                    'exception' => $e,
                ]);
                $output->writeln(sprintf('<error>Failed to purge band space %s: %s</error>', (string) $bandSpace->id, $e->getMessage()));
            }
        }

        $output->writeln(sprintf(
            '<info>%s %d band space(s) past their grace period</info>',
            $isDryRun ? 'Would delete' : 'Deleted',
            $purged,
        ));

        return $failures;
    }

    /**
     * Objects first, then the rows: deleteById() drops every child row by FK cascade, and a cascade at
     * database level never reaches VichUploader, so the storage prefix has to go on its own.
     */
    private function purgeBandSpace(BandSpace $bandSpace): void
    {
        $bandSpaceId = (string) $bandSpace->id;

        $this->musicallFilesystem->deleteDirectory(self::STORAGE_PREFIX . '/' . $bandSpaceId);

        $this->bandSpaceRepository->deleteById($bandSpaceId);
    }
}
