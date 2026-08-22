<?php

declare(strict_types=1);

namespace App\Command\BandSpace;

use App\Entity\BandSpace\BandSpaceActivity;
use App\Entity\BandSpace\BandSpaceNote;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceNoteActivityType;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fills band_space_note.created_by_id for the notes that predate the column, from the activity feed.
 *
 * The value is not inferred. BandSpaceNoteCreateProcessor records a note_created activity carrying the
 * new note's id and the member who asked for it, so the feed already holds the answer exactly, and
 * only that one type is read. The first member to rename or edit a note is not necessarily whoever
 * wrote it, and this column decides who may delete the note, so a plausible guess is worse than NULL.
 *
 * Notes created between 2026-03-07, when the module shipped, and 2026-05-08, when #648 wired the
 * recorder into the note processors, have no such row and keep nobody recorded. NoteOwnerChecker
 * leaves those admin-only, which is the intended outcome rather than a shortfall, so the counts below
 * report that remainder instead of trying to shrink it.
 *
 * Filling the column widens who may delete a note, from admins alone to its author as well. That is
 * the point of the backfill, and it is sound only because the source is authoritative.
 *
 * Safe to re-run: it considers only notes whose author is NULL, so it can never overwrite one, and a
 * second run over a filled database writes nothing.
 *
 * One-shot, and deliberately self-contained: both lookups go through Doctrine's own findBy, so this
 * file and its test are the whole feature. Delete the two of them once it has run in production and
 * nothing is left behind. That is also why neither query lives in a repository, the usual home for
 * them: a repository method would outlive the only caller and become dead code the day this goes.
 */
#[AsCommand(
    name: 'app:band-space:notes:backfill-created-by',
    description: 'Fill the author of band space notes written before the column existed, from their note_created activity',
)]
class BackfillNoteCreatedByCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be filled without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = (bool) $input->getOption('dry-run');
        if ($isDryRun) {
            $io->writeln('<comment>Dry run: nothing will be written</comment>');
        }

        $notes = $this->entityManager->getRepository(BandSpaceNote::class)->findBy(['createdBy' => null]);
        $withoutAuthor = count($notes);
        if ($withoutAuthor === 0) {
            $io->success('Every note already records who wrote it.');

            return Command::SUCCESS;
        }

        $actorsByNoteId = $this->findCreationActorsByNoteId($notes);

        $filled = 0;
        foreach ($notes as $note) {
            $actor = $actorsByNoteId[(string) $note->id] ?? null;
            if (!$actor instanceof User) {
                continue;
            }

            if (!$isDryRun) {
                $note->createdBy = $actor;
            }
            $filled++;
        }

        if (!$isDryRun) {
            $this->entityManager->flush();
        }

        $io->writeln(sprintf('Notes with nobody recorded: %d', $withoutAuthor));
        $io->writeln(sprintf('Recovered from a note_created activity: %d', $filled));
        $io->writeln(sprintf('Older than the activity feed, left as they are: %d', $withoutAuthor - $filled));

        $io->success(sprintf(
            '%s%d note(s) now record their author.',
            $isDryRun ? '[DRY-RUN] ' : '',
            $filled,
        ));

        return Command::SUCCESS;
    }

    /**
     * Who created each of the given notes, keyed by note id.
     *
     * Scoped to the ids handed in, so the query costs what is left to repair rather than growing with
     * every note ever created. band_space_activity.resource_id carries no foreign key, the feed being
     * generic over every module, so the pairing happens here rather than as a join.
     *
     * Ordered oldest first and first-write-wins, so a note somehow holding several note_created rows
     * resolves to the earliest. A row whose actor is NULL recovers nothing and is skipped.
     *
     * @param BandSpaceNote[] $notes
     *
     * @return array<string, User>
     */
    private function findCreationActorsByNoteId(array $notes): array
    {
        $noteIds = array_map(
            static fn(BandSpaceNote $note): UuidInterface => Uuid::fromString((string) $note->id),
            $notes,
        );

        $creationActivities = $this->entityManager->getRepository(BandSpaceActivity::class)->findBy(
            [
                'module' => BandSpaceModule::Notes,
                'type' => BandSpaceNoteActivityType::Created->value,
                'resourceId' => $noteIds,
            ],
            ['creationDatetime' => 'ASC'],
        );

        $actorsByNoteId = [];
        foreach ($creationActivities as $activity) {
            $noteId = (string) $activity->resourceId;
            if (isset($actorsByNoteId[$noteId]) || !$activity->actor instanceof User) {
                continue;
            }

            $actorsByNoteId[$noteId] = $activity->actor;
        }

        return $actorsByNoteId;
    }
}
