<?php declare(strict_types=1);

namespace App\Command\BandSpace;

use App\Entity\BandSpace\BandSpaceActivity;
use App\Entity\BandSpace\TaskActivity;
use App\Enum\BandSpace\BandSpaceModule;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:band-space:activity:backfill-task-activity',
    description: 'One-shot data migration: copy task_activity rows into band_space_activity (module=task). Delete this command once it has been run in production.'
)]
class BackfillTaskActivityCommand extends Command
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count rows without inserting anything')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Delete existing module=task rows in band_space_activity before reseeding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $reset = (bool) $input->getOption('reset');

        $sourceCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(ta.id)')
            ->from(TaskActivity::class, 'ta')
            ->getQuery()
            ->getSingleScalarResult();

        $existingCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(BandSpaceActivity::class, 'a')
            ->where('a.module = :module')
            ->setParameter('module', BandSpaceModule::Task)
            ->getQuery()
            ->getSingleScalarResult();

        $io->writeln(sprintf('task_activity rows: %d', $sourceCount));
        $io->writeln(sprintf('band_space_activity rows for module=task: %d', $existingCount));

        if ($existingCount > 0 && !$reset) {
            $io->warning('module=task rows already exist in band_space_activity. Re-run with --reset to wipe and reseed.');
            return Command::SUCCESS;
        }

        if ($reset && !$dryRun) {
            $deleted = (int) $this->entityManager->createQueryBuilder()
                ->delete(BandSpaceActivity::class, 'a')
                ->where('a.module = :module')
                ->setParameter('module', BandSpaceModule::Task)
                ->getQuery()
                ->execute();
            $io->writeln(sprintf('Deleted %d existing module=task rows.', $deleted));
        }

        if ($sourceCount === 0) {
            $io->success('Nothing to backfill — task_activity is empty.');
            return Command::SUCCESS;
        }

        $copied = 0;
        $query = $this->entityManager->createQueryBuilder()
            ->select('ta', 't', 'a')
            ->from(TaskActivity::class, 'ta')
            ->join('ta.task', 't')
            ->join('ta.actor', 'a')
            ->orderBy('ta.creationDatetime', 'ASC')
            ->getQuery();

        foreach ($query->toIterable() as $taskActivity) {
            assert($taskActivity instanceof TaskActivity);

            $activity = new BandSpaceActivity();
            $activity->bandSpace = $taskActivity->task->bandSpace;
            $activity->module = BandSpaceModule::Task;
            $activity->resourceId = Uuid::fromString((string) $taskActivity->task->id);
            $activity->actor = $taskActivity->actor;
            $activity->type = $taskActivity->type;
            $activity->payload = $taskActivity->payload;
            $activity->creationDatetime = $taskActivity->creationDatetime;

            if (!$dryRun) {
                $this->entityManager->persist($activity);
            }

            $copied++;
            if ($copied % self::BATCH_SIZE === 0) {
                if (!$dryRun) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
                $io->writeln(sprintf('Processed %d / %d', $copied, $sourceCount));
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%s%d activité(s) recopiée(s) depuis task_activity vers band_space_activity.',
            $dryRun ? '[DRY-RUN] ' : '',
            $copied,
        ));

        return Command::SUCCESS;
    }
}
