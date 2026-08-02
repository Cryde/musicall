<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\BandSpace\TechRider;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceRiderActivityType;
use App\Repository\BandSpace\TechRiderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, void>
 *
 * Soft delete: sets archiveDatetime. TechRiderUnarchiveProcessor is what undoes it.
 *
 * A second archive returns 409 rather than succeeding silently the way
 * SetlistDeleteProcessor does. Riders stay readable once archived, so the caller can
 * see the state it is asking for and a repeated call means a stale client, which is
 * worth surfacing. Same choice as BandSpaceFileRestoreProcessor.
 */
readonly class TechRiderDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TechRiderRepository $techRiderRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $techRider = $this->techRiderRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$techRider instanceof TechRider) {
            throw new NotFoundHttpException('Tech rider introuvable');
        }

        if ($techRider->isArchived()) {
            throw new ConflictHttpException('Ce tech rider est déjà archivé');
        }

        $techRider->archiveDatetime = new DateTimeImmutable();

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Rider,
            type: BandSpaceRiderActivityType::RiderArchived,
            resourceId: (string) $techRider->id,
            actor: $user,
            payload: ['name' => $techRider->name],
        );

        $this->entityManager->flush();
    }
}
