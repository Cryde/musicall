<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\SetlistItemResource;
use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\SetlistItem;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Repository\BandSpace\SetlistItemRepository;
use App\Repository\BandSpace\SetlistRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\SetlistItemBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<SetlistItemResource, SetlistItemResource>
 *
 * Update of item-level fields only (label/durationOverride/note/transition).
 * Type and song are immutable post-creation: callers must delete + recreate to
 * change them, which keeps the create-time validator (type/songId/label combo)
 * the single source of consistency truth.
 */
readonly class SetlistItemUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SetlistRepository $setlistRepository,
        private SetlistItemRepository $setlistItemRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private SetlistItemBuilder $itemBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param SetlistItemResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SetlistItemResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $setlist = $this->setlistRepository->findOneByIdAndBandSpace((string) $uriVariables['setlistId'], $bandSpace);
        if (!$setlist instanceof Setlist) {
            throw new NotFoundHttpException('Setlist introuvable');
        }

        $item = $this->setlistItemRepository->findOneByIdAndSetlist((string) $uriVariables['id'], $setlist);
        if (!$item instanceof SetlistItem) {
            throw new NotFoundHttpException('Item introuvable');
        }

        // Label is a snapshot/display field on any item type; create-time validator
        // already gates the song/label exclusivity, so PATCH just stores whatever
        // the client sends.
        $item->label = $data->label;
        $item->durationOverride = $data->durationOverride;
        $item->note = $data->note;
        $item->transition = $data->transition;

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::SetlistItemUpdated,
            resourceId: (string) $setlist->id,
            actor: $user,
            payload: ['item_id' => (string) $item->id],
        );

        $this->entityManager->flush();

        return $this->itemBuilder->buildItem($item);
    }
}
