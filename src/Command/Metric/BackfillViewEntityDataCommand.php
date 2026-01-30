<?php

declare(strict_types=1);

namespace App\Command\Metric;

use App\Contracts\Metric\ViewableInterface;
use App\Entity\Gallery;
use App\Entity\Metric\ViewCache;
use App\Entity\Musician\MusicianProfile;
use App\Entity\Publication;
use App\Entity\User\UserProfile;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:metric:backfill-view-entity-data',
    description: 'Backfill entity_type and entity_id fields for existing View records'
)]
class BackfillViewEntityDataCommand extends Command
{
    private const int BATCH_SIZE = 500;

    /** @var array<class-string<ViewableInterface>> */
    private const array VIEWABLE_ENTITIES = [
        Publication::class,
        Gallery::class,
        UserProfile::class,
        MusicianProfile::class,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    private function addStatusFilter(QueryBuilder $qb, string $entityClass): void
    {
        match ($entityClass) {
            Publication::class => $qb->andWhere('e.status = :status')->setParameter('status', Publication::STATUS_ONLINE),
            Gallery::class => $qb->andWhere('e.status = :status')->setParameter('status', Gallery::STATUS_ONLINE),
            default => null,
        };
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without making changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Dry run mode - no changes will be made');
        }

        $io->title('Backfilling View entity data');

        $totalUpdated = 0;
        $pendingUpdates = 0;

        foreach (self::VIEWABLE_ENTITIES as $entityClass) {
            [$updated, $pendingUpdates] = $this->processEntity($entityClass, $io, $dryRun, $pendingUpdates);
            $totalUpdated += $updated;
        }

        if (!$dryRun && $pendingUpdates > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf('Backfill complete. %d View records updated.', $totalUpdated));

        return Command::SUCCESS;
    }

    /**
     * @param class-string<ViewableInterface> $entityClass
     *
     * @return array{int, int} [updatedCount, pendingUpdates]
     */
    private function processEntity(string $entityClass, SymfonyStyle $io, bool $dryRun, int $pendingUpdates): array
    {
        $shortName = (new \ReflectionClass($entityClass))->getShortName();
        $io->section(sprintf('Processing %s', $shortName));

        $qb = $this->entityManager->getRepository($entityClass)
            ->createQueryBuilder('e')
            ->where('e.viewCache IS NOT NULL');

        $this->addStatusFilter($qb, $entityClass);

        $entities = $qb->getQuery()->getResult();

        $updatedCount = 0;

        /** @var ViewableInterface $entity */
        foreach ($entities as $entity) {
            $viewCache = $entity->getViewCache();
            if (!$viewCache instanceof ViewCache) {
                continue;
            }

            $viewsUpdated = $this->updateViewsForViewCache(
                $viewCache,
                $entity->getViewableType(),
                $entity->getViewableId(),
                $dryRun
            );

            $updatedCount += $viewsUpdated;
            $pendingUpdates += $viewsUpdated;

            if (!$dryRun && $pendingUpdates >= self::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $pendingUpdates = 0;
                $io->text(sprintf('  Flushed batch (%d total so far)', $updatedCount));
            }
        }

        $io->text(sprintf('  Found %d entities with ViewCache, updated %d View records', count($entities), $updatedCount));

        return [$updatedCount, $pendingUpdates];
    }

    private function updateViewsForViewCache(
        ViewCache $viewCache,
        string $entityType,
        ?string $entityId,
        bool $dryRun,
    ): int {
        $views = $this->entityManager->getRepository(\App\Entity\Metric\View::class)
            ->createQueryBuilder('v')
            ->where('v.viewCache = :viewCache')
            ->andWhere('v.entityType IS NULL')
            ->setParameter('viewCache', $viewCache)
            ->getQuery()
            ->getResult();

        if (!$dryRun) {
            foreach ($views as $view) {
                $view->setEntityType($entityType);
                $view->setEntityId($entityId);
            }
        }

        return count($views);
    }
}
