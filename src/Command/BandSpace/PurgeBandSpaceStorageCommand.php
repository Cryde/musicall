<?php

declare(strict_types=1);

namespace App\Command\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Service\BandSpace\File\BandSpaceFilePurger;
use DateTimeImmutable;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
 *
 * Archived setlists and songs are deliberately NOT swept (#761). Files get a retention window because
 * every archived one holds bytes that cost money and count against the band's quota; a setlist row and
 * a song row cost neither, so the deadline would buy nothing and would only add a way to lose work.
 * Songs in particular must never be destroyed on a timer: a SetlistItem keeps pointing at its song
 * after the song is archived, which is what lets last year's setlist still render and export, so
 * purging the row would quietly gut setlists nobody archived. Their trash is unbounded on purpose.
 */
#[AsCommand(
    name: 'app:band-space:purge',
    description: 'Delete archived Band Space files and spaces past their grace period, from the database and from object storage',
)]
class PurgeBandSpaceStorageCommand extends Command
{
    /**
     * Mirrors the directory namer of the band_space_file mapping (config/packages/vich_uploader.yaml):
     * every object of a space lives under this prefix, so a space can be wiped in one batched call.
     */
    private const string STORAGE_PREFIX = 'band_space_files';

    public function __construct(
        private readonly BandSpaceRepository $bandSpaceRepository,
        private readonly BandSpaceFileRepository $bandSpaceFileRepository,
        private readonly BandSpaceFilePurger $filePurger,
        private readonly FilesystemOperator $musicallFilesystem,
        private readonly LoggerInterface $logger,
        #[Autowire('%band_space.file_retention_days%')]
        private readonly int $retentionDays,
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
                (string) $this->retentionDays,
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
     * The destruction itself lives in BandSpaceFilePurger, shared with the trash's delete-permanently
     * endpoint. It also declines files restored since this batch was read, so a file skipped here is not
     * a failure.
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
                if (!$this->filePurger->purge($file)) {
                    $output->writeln(sprintf('  skipped file %s, restored since this run started', (string) $file->id));

                    continue;
                }
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
