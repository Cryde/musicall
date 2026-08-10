<?php

declare(strict_types=1);

namespace App\Command\BandSpace;

use App\Entity\BandSpace\BandSpaceNote;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Service\BandSpace\NoteContentEncodingInspector;
use App\Service\BandSpace\NoteContentEncodingReport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rewrites the note bodies the read path removed by #808 entity encoded (#859).
 *
 * That path only broke the note on the way out, but the editor autosaved what it was shown, so the
 * entities reached the database and stayed there once the fix landed. This is the only way back for
 * the rows already in that state, and it is a one shot: nothing writes that encoding any more.
 *
 * Reports by default and writes only on --write, because the detection rule cannot be right in every
 * case and the operator is the last check. Idempotent: a repaired body no longer matches, so a second
 * run is a no op.
 *
 * Three lists come out of a run, and they are printed apart because they ask different things of the
 * reader. Notes attributed node by node need no reading at all. Notes carrying at least one inferred
 * text are repaired too, but one of their paragraphs is only attributed by a neighbour, and a
 * paragraph pasted or typed after the fix has no such neighbour, so every inferred change is printed
 * in full and has to be read before --write. Notes left for review are not touched at all.
 */
#[AsCommand(
    name: 'app:band-space:repair-note-encoding',
    description: 'Repair band space notes whose text was entity encoded by the read path fixed in #808',
)]
class RepairNoteContentEncodingCommand extends Command
{
    private const string DEFAULT_SAMPLE_SIZE = '10';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BandSpaceNoteRepository $noteRepository,
        private readonly NoteContentEncodingInspector $inspector,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('write', null, InputOption::VALUE_NONE, 'Apply the repair; without it the command only reports')
            ->addOption(
                'sample',
                's',
                InputOption::VALUE_REQUIRED,
                'Maximum number of attributed text changes to print, 0 for all of them; inferred changes are always printed in full',
                self::DEFAULT_SAMPLE_SIZE,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sampleSize = (int) $input->getOption('sample');
        if ($sampleSize < 0) {
            $io->error('Option --sample cannot be negative');

            return Command::FAILURE;
        }

        $notes = $this->noteRepository->findAllWithContent();

        /** @var list<array{note: BandSpaceNote, report: NoteContentEncodingReport}> $attributed */
        $attributed = [];
        /** @var list<array{note: BandSpaceNote, report: NoteContentEncodingReport}> $inferred */
        $inferred = [];
        /** @var list<array{note: BandSpaceNote, report: NoteContentEncodingReport}> $review */
        $review = [];

        foreach ($notes as $note) {
            $report = $this->inspector->inspect($note->content);

            if ($report->isRepairable()) {
                // Classified by the weakest evidence any of its rewritten texts rests on, so a note
                // can never reach the attributed list by carrying one proven paragraph.
                if ($report->isInferred()) {
                    $inferred[] = ['note' => $note, 'report' => $report];
                } else {
                    $attributed[] = ['note' => $note, 'report' => $report];
                }
            } elseif ($report->needsReview()) {
                $review[] = ['note' => $note, 'report' => $report];
            }
        }

        $io->title('Band Space notes: entity encoded content');
        $io->writeln(sprintf('%d note(s) with a body scanned', count($notes)));
        $io->writeln(sprintf('%d note(s) to repair, attributed node by node', count($attributed)));
        $io->writeln(sprintf('%d note(s) to repair with at least one inferred text', count($inferred)));
        $io->writeln(sprintf('%d note(s) left for a manual review', count($review)));

        $this->reportRepairs($io, 'Notes to repair, attributed node by node', $attributed, $sampleSize);
        $this->reportRepairs($io, 'Notes to repair with at least one inferred text, read these before writing', $inferred, 0);
        $this->reportReview($io, $review);

        $repairable = [...$attributed, ...$inferred];
        if ($repairable === []) {
            $io->success('No note to repair.');

            return Command::SUCCESS;
        }

        if (!(bool) $input->getOption('write')) {
            $io->warning('Nothing was written. Re-run with --write to apply the repair.');

            return Command::SUCCESS;
        }

        foreach ($repairable as $row) {
            $note = $row['note'];
            $note->content = $row['report']->repairedContent;

            // The revision is bumped so a member who still has the pre repair body open is refused
            // by the guard of #809 on their next autosave instead of quietly writing the entities
            // back over the repair. updateDatetime is left alone on purpose: the note is being put
            // back to what its author wrote, and dating that today, in a column the band reads as
            // "last edited", would be a worse lie than saying nothing. No activity is recorded for
            // the same reason.
            ++$note->contentVersion;
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Repaired %d note(s), %d of them carrying an inferred text.',
            count($repairable),
            count($inferred),
        ));

        return Command::SUCCESS;
    }

    /**
     * @param list<array{note: BandSpaceNote, report: NoteContentEncodingReport}> $rows
     * @param int $sampleSize the number of change pairs to print, 0 for all of them
     */
    private function reportRepairs(SymfonyStyle $io, string $title, array $rows, int $sampleSize): void
    {
        if ($rows === []) {
            return;
        }

        $io->section($title);

        $budget = $sampleSize === 0 ? PHP_INT_MAX : $sampleSize;
        $hidden = 0;

        foreach ($rows as $row) {
            $io->writeln(' * ' . $this->describe($row['note']));

            foreach ($row['report']->changes as $change) {
                if ($budget === 0) {
                    ++$hidden;

                    continue;
                }

                if ($change['inferred']) {
                    $io->writeln('     inferred, attributed only by another node of the same note:');
                }

                // Escaped because a repaired text is allowed to hold a raw < again, and the console
                // formatter would read that as a style tag and swallow it.
                $io->writeln('     - ' . OutputFormatter::escape($change['before']));
                $io->writeln('     + ' . OutputFormatter::escape($change['after']));
                --$budget;
            }
        }

        if ($hidden > 0) {
            $io->writeln(sprintf('   %d further text change(s) not shown, raise --sample to see them', $hidden));
        }
    }

    /**
     * @param list<array{note: BandSpaceNote, report: NoteContentEncodingReport}> $review
     */
    private function reportReview(SymfonyStyle $io, array $review): void
    {
        if ($review === []) {
            return;
        }

        $io->section('Notes left for a manual review');
        $io->writeln('Nothing is decided for these and --write skips them. Open each one in the editor and repair it by hand if it reads wrong.');

        foreach ($review as $row) {
            $io->writeln(' * ' . $this->describe($row['note']));
            $io->writeln('     ' . OutputFormatter::escape((string) $row['report']->reviewReason));
        }
    }

    private function describe(BandSpaceNote $note): string
    {
        return OutputFormatter::escape(sprintf(
            '%s  "%s"  (band space: %s)',
            (string) $note->id,
            $note->title,
            $note->bandSpace->name,
        ));
    }
}
