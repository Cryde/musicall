<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\TechRider\TechRiderItemResource;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderItem;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceRiderActivityType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\TechRiderRepository;
use App\Repository\BandSpace\TechRiderItemRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\TechRiderWriteGuard;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\TechRider\TechRiderItemBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TechRiderItemResource, TechRiderItemResource>
 */
readonly class TechRiderItemUpdateProcessor implements ProcessorInterface
{
    /**
     * A content change by the same person on the same item inside this window reuses the
     * existing activity row instead of adding one. The editor autosaves on a debounce, so
     * without this an afternoon of writing becomes forty near identical feed entries.
     */
    private const int ACTIVITY_COALESCE_MINUTES = 15;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TechRiderWriteGuard $writeGuard,
        private TechRiderRepository $techRiderRepository,
        private TechRiderItemRepository $itemRepository,
        private BandSpaceActivityRepository $activityRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private TechRiderItemBuilder $itemBuilder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param TechRiderItemResource $data
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

        $item = $this->itemRepository->findOneByIdAndRider((string) $uriVariables['id'], $techRider);
        if (!$item instanceof TechRiderItem) {
            throw new NotFoundHttpException('Élément introuvable');
        }

        // Absent and null mean different things here: a title-only save must not erase the
        // content, and clearing the content is a legitimate request. Only the raw payload
        // distinguishes them, the deserialized resource cannot.
        $payload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        $titleChanged = false;
        if (array_key_exists('title', $payload) && $item->title !== $data->title) {
            $item->title = $data->title;
            $titleChanged = true;
        }

        $contentChanged = false;
        if (array_key_exists('content', $payload) && $item->content !== $data->content) {
            $item->content = $data->content;
            $contentChanged = true;
        }

        // Composing the document, not editing it: no activity, because toggling an item in
        // and out while deciding what to send is not a change anyone needs a feed entry for.
        $inclusionChanged = false;
        if (array_key_exists('is_included', $payload) && $item->isIncluded !== $data->isIncluded) {
            $item->isIncluded = $data->isIncluded;
            $inclusionChanged = true;
        }

        if (!$titleChanged && !$contentChanged && !$inclusionChanged) {
            return $this->itemBuilder->buildItem($item);
        }

        $item->updateDatetime = new DateTime();

        // A rename is a discrete act and is always recorded; only the autosaved content is
        // coalesced.
        if ($titleChanged) {
            $this->activityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Rider,
                type: BandSpaceRiderActivityType::RiderItemRenamed,
                resourceId: (string) $item->id,
                actor: $user,
                payload: ['rider_name' => $techRider->name, 'title' => $item->title],
            );
        }

        if ($contentChanged && $this->shouldRecordContentUpdate($bandSpace, $item, $user)) {
            $this->activityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Rider,
                type: BandSpaceRiderActivityType::RiderItemUpdated,
                resourceId: (string) $item->id,
                actor: $user,
                payload: ['rider_name' => $techRider->name, 'title' => $item->title],
            );
        }

        $this->entityManager->flush();

        return $this->itemBuilder->buildItem($item);
    }

    private function shouldRecordContentUpdate(BandSpace $bandSpace, TechRiderItem $item, User $user): bool
    {
        $latest = $this->activityRepository->findLatestForResource(
            $bandSpace,
            BandSpaceModule::Rider,
            BandSpaceRiderActivityType::RiderItemUpdated->value,
            (string) $item->id,
            $user,
        );

        if ($latest === null) {
            return true;
        }

        return $latest->creationDatetime < new DateTime(sprintf('-%d minutes', self::ACTIVITY_COALESCE_MINUTES));
    }
}
