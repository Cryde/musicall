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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<SetlistItemResource, void>
 */
readonly class SetlistItemDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SetlistRepository $setlistRepository,
        private SetlistItemRepository $setlistItemRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private Security $security,
    ) {
    }

    /**
     * Hard delete + collapse positions of trailing items so the sequence stays
     * dense (0..n-1). One activity row.
     *
     * @param SetlistItemResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
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

        $removedPosition = $item->position;
        $removedId = (string) $item->id;

        $this->entityManager->remove($item);

        $collapsed = [];
        foreach ($setlist->items as $sibling) {
            if ((string) $sibling->id === $removedId) {
                continue;
            }
            if ($sibling->position > $removedPosition) {
                $sibling->position--;
                $collapsed[] = ['id' => (string) $sibling->id, 'position' => $sibling->position];
            }
        }

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::SetlistItemRemoved,
            resourceId: (string) $setlist->id,
            actor: $user,
            payload: ['item_id' => $removedId],
        );

        $this->entityManager->flush();
    }
}
