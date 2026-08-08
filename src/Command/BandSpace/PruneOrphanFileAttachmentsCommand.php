<?php

declare(strict_types=1);

namespace App\Command\BandSpace;

use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Service\BandSpace\File\BandSpaceFileSourceTypes;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clears the attachment rows left behind by sources deleted before #818 closed the leak.
 *
 * band_space_file_attachment.source_id has no foreign key, so until that fix every deleted task, note
 * or finance entry left its attachments in place. An attachment nothing points at is not harmless: the
 * file it names cannot be deleted (BandSpaceFileDeleteProcessor refuses to trash an attached file) and
 * the detach endpoint that would release it answers 404, its source being gone. The file is stuck in
 * the band's quota for good, and this command is the only way out for the rows already in that state.
 *
 * Only the attachment row goes. The file stays in the library, exactly as it does when a source is
 * deleted today, and becomes deletable again through the normal flow.
 *
 * Safe to re-run: it deletes only what it finds orphaned at that moment, so a second run over a clean
 * database deletes nothing. A soft-deleted source (an archived Song, Setlist or Task) still has its
 * row and is therefore never treated as an orphan.
 */
#[AsCommand(
    name: 'app:band-space:prune-orphan-attachments',
    description: 'Delete band space file attachments whose task, note, finance entry, song or setlist no longer exists',
)]
class PruneOrphanFileAttachmentsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BandSpaceFileAttachmentRepository $attachmentRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be deleted without deleting anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = (bool) $input->getOption('dry-run');
        if ($isDryRun) {
            $output->writeln('<comment>Dry run: nothing will be deleted</comment>');
        }

        $total = 0;

        foreach (BandSpaceFileSourceTypes::ENTITY_BY_TYPE as $sourceType => $sourceEntityClass) {
            $orphans = $this->attachmentRepository->findOrphansBySourceType($sourceType, $sourceEntityClass);
            if ($orphans === []) {
                continue;
            }

            $output->writeln(sprintf('  %s: %d orphan attachment(s)', $sourceType, count($orphans)));
            $total += count($orphans);

            if ($isDryRun) {
                continue;
            }

            // No activity is recorded. These detachments happened whenever the source was deleted,
            // possibly months ago and by a member who has since left; dating them today, in a feed the
            // band reads as a timeline, would be a worse lie than saying nothing.
            foreach ($orphans as $orphan) {
                $this->entityManager->remove($orphan);
            }
            $this->entityManager->flush();
        }

        $output->writeln(sprintf(
            '<info>%s %d orphan attachment(s)</info>',
            $isDryRun ? 'Would delete' : 'Deleted',
            $total,
        ));

        return Command::SUCCESS;
    }
}
