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
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TechRiderResource, TechRiderResource>
 */
readonly class TechRiderUpdateProcessor implements ProcessorInterface
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

    /**
     * @param TechRiderResource $data
     *
     * Only `name` is mutable through PATCH (rename). The other fields on TechRiderResource
     * are hydrated by the provider and ignored here, matching SetlistUpdateProcessor.
     */
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

        $techRider->name = $data->name;
        $techRider->updateDatetime = new DateTime();

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Rider,
            type: BandSpaceRiderActivityType::RiderRenamed,
            resourceId: (string) $techRider->id,
            actor: $user,
            payload: ['name' => $techRider->name],
        );

        $this->entityManager->flush();

        return $this->techRiderBuilder->buildItem($techRider);
    }
}
