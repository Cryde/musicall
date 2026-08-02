<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\TechRider\TechRiderItemCreate;
use App\ApiResource\BandSpace\TechRider\TechRiderItemResource;
use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderItem;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceRiderActivityType;
use App\Enum\BandSpace\TechRiderItemType;
use App\Repository\BandSpace\TechRiderRepository;
use App\Repository\BandSpace\TechRiderItemRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\TechRiderWriteGuard;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\TechRider\TechRiderItemBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TechRiderItemCreate, TechRiderItemResource>
 */
readonly class TechRiderItemCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TechRiderWriteGuard $writeGuard,
        private TechRiderRepository $techRiderRepository,
        private TechRiderItemRepository $itemRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private TechRiderItemBuilder $itemBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param TechRiderItemCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TechRiderItemResource
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

        $this->writeGuard->assertWritable($techRider);

        $item = new TechRiderItem();
        $item->techRider = $techRider;
        $item->type = TechRiderItemType::from($data->type);
        $item->title = $data->title;
        $item->content = $data->content;
        // Appended, never inserted: a new item is written at the end and moved by reorder.
        $item->position = $this->itemRepository->nextPosition($techRider);

        $this->entityManager->persist($item);

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Rider,
            type: BandSpaceRiderActivityType::RiderItemAdded,
            resourceId: (string) $item->id,
            actor: $user,
            payload: ['rider_name' => $techRider->name, 'title' => $item->title],
        );

        $this->entityManager->flush();

        return $this->itemBuilder->buildItem($item);
    }
}
