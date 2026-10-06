<?php declare(strict_types=1);

namespace App\State\Processor\Report;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Report\ReportCreate;
use App\Entity\Report\Report;
use App\Entity\User;
use App\Enum\Report\ReportReason;
use App\Enum\Report\ReportTargetType;
use App\Event\ReportCreatedEvent;
use App\Repository\Report\ReportRepository;
use App\Service\Report\ReportTarget;
use App\Service\Report\Target\ReportTargetLoaderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProcessorInterface<ReportCreate, void>
 */
readonly class ReportCreateProcessor implements ProcessorInterface
{
    /** @var array<string, ReportTargetLoaderInterface> */
    private array $loaders;

    /**
     * @param iterable<ReportTargetLoaderInterface> $loaders
     */
    public function __construct(
        #[AutowireIterator('app.report_target_loader')]
        iterable $loaders,
        private ReportRepository $reportRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
        #[Target('report_create')]
        private RateLimiterFactoryInterface $reportCreateLimiter,
        private EventDispatcherInterface $eventDispatcher,
    ) {
        $byType = [];
        foreach ($loaders as $loader) {
            $byType[$loader->type()->value] = $loader;
        }
        $this->loaders = $byType;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $reporter = $this->security->getUser();
        if (!$reporter instanceof User) {
            throw new AccessDeniedHttpException();
        }

        // Every call, not only the ones that create: a 404 tells whether something exists and is
        // visible, so probing must cost the same as reporting.
        $this->reportCreateLimiter->create($reporter->getUserIdentifier())->consume()->ensureAccepted();

        $targetType = ReportTargetType::from($data->targetType);
        $targetId = $targetType->canonicalId($data->targetId);
        $loader = $this->loaders[$targetType->value] ?? throw new \LogicException(sprintf('No report target loader for "%s"', $targetType->value));
        $target = $loader->load($targetId, $reporter);
        if (!$target instanceof ReportTarget) {
            throw new NotFoundHttpException('Contenu introuvable');
        }

        // Reporting oneself, or reporting again while the first is pending, changes nothing.
        $isSelf = $target->author?->id === $reporter->id;
        if ($isSelf || $this->reportRepository->findPendingByReporterAndTarget($reporter, $targetType, $targetId) instanceof Report) {
            return;
        }

        $report = new Report();
        $report->reporter = $reporter;
        $report->targetType = $targetType;
        $report->targetId = $targetId;
        $report->targetAuthor = $target->author;
        $report->reason = ReportReason::from($data->reason);
        $report->details = $data->details;
        $report->snapshotText = $target->text;
        $report->snapshotContext = $target->context;

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new ReportCreatedEvent($report));
    }
}
