<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\SetlistReorder;
use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\SetlistItem;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Repository\BandSpace\SetlistItemRepository;
use App\Repository\BandSpace\SetlistRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<SetlistReorder, void>
 */
readonly class SetlistReorderProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SetlistRepository $setlistRepository,
        private SetlistItemRepository $itemRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private Security $security,
    ) {
    }

    /**
     * @param SetlistReorder $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $setlist = $this->setlistRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$setlist instanceof Setlist) {
            throw new NotFoundHttpException('Setlist introuvable');
        }

        $requestedIds = array_column($data->positions, 'id');
        $foundItems = $this->itemRepository->findByIdsAndSetlist($requestedIds, $setlist);
        $foundIds = array_map(fn (SetlistItem $item): string => (string) $item->id, $foundItems);

        $missingIds = array_diff($requestedIds, $foundIds);
        if (count($missingIds) > 0) {
            throw new BadRequestHttpException(sprintf('Item %s introuvable dans ce setlist', reset($missingIds)));
        }

        // Reorder must cover every item in the setlist (no partial reorders).
        if (count($foundItems) !== $setlist->items->count()) {
            throw new UnprocessableEntityHttpException('Le réordonnancement doit inclure tous les items du setlist');
        }

        $this->itemRepository->bulkUpdatePositions($data->positions);

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::SetlistItemReordered,
            resourceId: (string) $setlist->id,
            actor: $user,
            payload: ['count' => count($data->positions)],
        );

        $this->entityManager->flush();
    }
}
