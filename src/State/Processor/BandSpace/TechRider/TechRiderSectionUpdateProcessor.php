<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\TechRider\TechRiderSectionResource;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderSection;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceRiderActivityType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\TechRiderRepository;
use App\Repository\BandSpace\TechRiderSectionRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\TechRider\TechRiderSectionBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TechRiderSectionResource, TechRiderSectionResource>
 */
readonly class TechRiderSectionUpdateProcessor implements ProcessorInterface
{
    /**
     * A content change by the same person on the same section inside this window reuses the
     * existing activity row instead of adding one. The editor autosaves on a debounce, so
     * without this an afternoon of writing becomes forty near identical feed entries.
     */
    private const int ACTIVITY_COALESCE_MINUTES = 15;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TechRiderRepository $techRiderRepository,
        private TechRiderSectionRepository $sectionRepository,
        private BandSpaceActivityRepository $activityRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private TechRiderSectionBuilder $sectionBuilder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param TechRiderSectionResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TechRiderSectionResource
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

        $section = $this->sectionRepository->findOneByIdAndRider((string) $uriVariables['id'], $techRider);
        if (!$section instanceof TechRiderSection) {
            throw new NotFoundHttpException('Section introuvable');
        }

        // Absent and null mean different things here: a title-only save must not erase the
        // content, and clearing the content is a legitimate request. Only the raw payload
        // distinguishes them, the deserialized resource cannot.
        $payload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        $titleChanged = false;
        if (array_key_exists('title', $payload) && $section->title !== $data->title) {
            $section->title = $data->title;
            $titleChanged = true;
        }

        $contentChanged = false;
        if (array_key_exists('content', $payload) && $section->content !== $data->content) {
            $section->content = $data->content;
            $contentChanged = true;
        }

        if (!$titleChanged && !$contentChanged) {
            return $this->sectionBuilder->buildItem($section);
        }

        $section->updateDatetime = new DateTime();

        // A rename is a discrete act and is always recorded; only the autosaved content is
        // coalesced.
        if ($titleChanged) {
            $this->activityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Rider,
                type: BandSpaceRiderActivityType::RiderSectionRenamed,
                resourceId: (string) $section->id,
                actor: $user,
                payload: ['rider_name' => $techRider->name, 'title' => $section->title],
            );
        }

        if ($contentChanged && $this->shouldRecordContentUpdate($bandSpace, $section, $user)) {
            $this->activityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Rider,
                type: BandSpaceRiderActivityType::RiderSectionUpdated,
                resourceId: (string) $section->id,
                actor: $user,
                payload: ['rider_name' => $techRider->name, 'title' => $section->title],
            );
        }

        $this->entityManager->flush();

        return $this->sectionBuilder->buildItem($section);
    }

    private function shouldRecordContentUpdate(BandSpace $bandSpace, TechRiderSection $section, User $user): bool
    {
        $latest = $this->activityRepository->findLatestForResource(
            $bandSpace,
            BandSpaceModule::Rider,
            BandSpaceRiderActivityType::RiderSectionUpdated->value,
            (string) $section->id,
            $user,
        );

        if ($latest === null) {
            return true;
        }

        return $latest->creationDatetime < new DateTime(sprintf('-%d minutes', self::ACTIVITY_COALESCE_MINUTES));
    }
}
