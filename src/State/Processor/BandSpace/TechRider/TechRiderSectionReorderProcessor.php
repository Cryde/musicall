<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\TechRider\TechRiderSectionReorder;
use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderSection;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceRiderActivityType;
use App\Repository\BandSpace\TechRiderRepository;
use App\Repository\BandSpace\TechRiderSectionRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<TechRiderSectionReorder, void>
 */
readonly class TechRiderSectionReorderProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TechRiderRepository $techRiderRepository,
        private TechRiderSectionRepository $sectionRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private Security $security,
    ) {
    }

    /**
     * @param TechRiderSectionReorder $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $techRider = $this->techRiderRepository->findOneByIdAndBandSpace((string) $uriVariables['riderId'], $bandSpace);
        if (!$techRider instanceof TechRider) {
            throw new NotFoundHttpException('Tech rider introuvable');
        }

        $sections = $this->sectionRepository->findByRider($techRider);
        $byId = [];
        foreach ($sections as $section) {
            $byId[(string) $section->id] = $section;
        }

        /** @var array<string, int> $requested */
        $requested = [];
        foreach ($data->positions as $item) {
            /** @var array{id: string, position: int} $item */
            $requested[$item['id']] = $item['position'];
        }

        // The payload must name every section of this rider and nothing else. A partial set
        // would leave the sections it omitted holding positions that now collide with the
        // ones it moved, so the order after the write would depend on tie-breaking rather
        // than on what was asked for. The shape rule (a contiguous 0..n-1) is enforced by
        // TechRiderSectionPositions; this is the membership half, which needs the rider.
        $requestedIds = array_keys($requested);
        $knownIds = array_keys($byId);
        sort($requestedIds);
        sort($knownIds);

        if ($requestedIds !== $knownIds) {
            throw new UnprocessableEntityHttpException(
                'Les positions doivent couvrir exactement les sections de ce tech rider',
            );
        }

        foreach ($requested as $id => $position) {
            $byId[$id]->position = $position;
        }

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Rider,
            type: BandSpaceRiderActivityType::RiderSectionReordered,
            resourceId: (string) $techRider->id,
            actor: $user,
            payload: ['rider_name' => $techRider->name, 'count' => count($requested)],
        );

        $this->entityManager->flush();
    }
}
