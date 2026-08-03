<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\TechRider\TechRiderItemResource;
use App\ApiResource\BandSpace\TechRider\TechRiderStagePlotInput;
use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderItem;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceRiderActivityType;
use App\Enum\BandSpace\TechRiderItemType;
use App\Repository\BandSpace\TechRiderItemRepository;
use App\Repository\BandSpace\TechRiderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\TechRiderWriteGuard;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\TechRider\TechRiderItemBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<TechRiderStagePlotInput, TechRiderItemResource>
 */
readonly class TechRiderStagePlotUpdateProcessor implements ProcessorInterface
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
     * @param TechRiderStagePlotInput $data
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

        $item = $this->itemRepository->findOneByIdAndRider((string) $uriVariables['itemId'], $techRider);
        if (!$item instanceof TechRiderItem) {
            throw new NotFoundHttpException('Élément introuvable');
        }

        if ($item->type !== TechRiderItemType::StagePlot) {
            throw new UnprocessableEntityHttpException(
                'Seul un élément de type plan de scène peut contenir un plan de scène',
            );
        }

        // The plot is the item's content. Writing it through this endpoint rather than the generic
        // item PATCH is what gives it a validator of its own; the PATCH would only see an opaque
        // blob and check its size.
        $item->content = $data->plot;
        $item->updateDatetime = new DateTime();

        // An explicit save of a whole document, so no coalescing: the editor saves on a button,
        // not on a debounce.
        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Rider,
            type: BandSpaceRiderActivityType::RiderStagePlotUpdated,
            resourceId: (string) $item->id,
            actor: $user,
            payload: [
                'rider_name' => $techRider->name,
                'title' => $item->title,
                'element_count' => count($data->plot['elements'] ?? []),
            ],
        );

        $this->entityManager->flush();

        return $this->itemBuilder->buildItem($item);
    }
}
