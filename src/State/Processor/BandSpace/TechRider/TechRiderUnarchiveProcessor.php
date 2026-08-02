<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\TechRider\TechRiderResource;
use App\Entity\BandSpace\TechRider;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceRiderActivityType;
use App\Repository\BandSpace\TechRiderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\TechRider\TechRiderBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, TechRiderResource>
 *
 * Takes a rider back out of the archive. Mirrors TechRiderDeleteProcessor, which is
 * what put it there.
 *
 * Setlist declares a setlist_unarchived activity type but has no endpoint behind it,
 * so its archive is one way. Riders are made per tour and next year's is built from
 * last year's, so the return path exists from the start here.
 */
readonly class TechRiderUnarchiveProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TechRiderRepository $techRiderRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private TechRiderBuilder $techRiderBuilder,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TechRiderResource
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

        if (!$techRider->isArchived()) {
            throw new ConflictHttpException('Ce tech rider n\'est pas archivé');
        }

        $techRider->archiveDatetime = null;

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Rider,
            type: BandSpaceRiderActivityType::RiderUnarchived,
            resourceId: (string) $techRider->id,
            actor: $user,
            payload: ['name' => $techRider->name],
        );

        $this->entityManager->flush();

        return $this->techRiderBuilder->buildItem($techRider);
    }
}
