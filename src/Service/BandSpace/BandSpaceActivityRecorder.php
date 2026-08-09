<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceActivity;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use BackedEnum;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

readonly class BandSpaceActivityRecorder
{
    /**
     * How long one member's work on one resource counts as a single act.
     *
     * Both surfaces with a rich text editor, notes and tech rider items, save on a two second
     * debounce, so recording every write turns one writing session into dozens of near identical
     * feed rows. The dashboard widget shows only the ten most recent activities of the whole space,
     * so that one session pushes agenda, finance, files and tasks off every member's dashboard.
     *
     * Fifteen minutes is long enough to cover a continuous session and short enough that a resource
     * picked up again after a break still leaves a trace.
     */
    private const int COALESCE_WINDOW_MINUTES = 15;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceActivityRepository $activityRepository,
    ) {
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function record(
        BandSpace $bandSpace,
        BandSpaceModule $module,
        BackedEnum|string $type,
        UuidInterface|string|null $resourceId = null,
        ?User $actor = null,
        ?array $payload = null,
    ): BandSpaceActivity {
        $activity = new BandSpaceActivity();
        $activity->bandSpace = $bandSpace;
        $activity->module = $module;
        $activity->resourceId = is_string($resourceId) ? Uuid::fromString($resourceId) : $resourceId;
        $activity->actor = $actor;
        $activity->type = $type instanceof BackedEnum ? (string) $type->value : $type;
        $activity->payload = $payload;

        $this->entityManager->persist($activity);

        return $activity;
    }

    /**
     * Records nothing when the same member already logged the same thing on the same resource
     * inside the window. For anything written by an autosave timer, which is what both rich text
     * editors do, this is what keeps the feed a history rather than a keystroke log.
     *
     * The resource and the actor are required here, unlike on `record`: "the same subject by the
     * same person" is the whole definition of what may be folded together, and neither half can be
     * left out. It stays per member on purpose, because two people writing in the same note are two
     * facts and collapsing them would hide who did what.
     *
     * Only a save is ever coalesced. A rename, an emoji pick or an inclusion toggle is a single
     * deliberate act, so callers record those every time.
     *
     * @param array<string, mixed>|null $payload
     *
     * @return BandSpaceActivity|null null when the write was folded into the existing entry
     */
    public function recordCoalesced(
        BandSpace $bandSpace,
        BandSpaceModule $module,
        BackedEnum|string $type,
        UuidInterface|string $resourceId,
        User $actor,
        ?array $payload = null,
    ): ?BandSpaceActivity {
        $typeValue = $type instanceof BackedEnum ? (string) $type->value : $type;

        $latest = $this->activityRepository->findLatestForResource(
            $bandSpace,
            $module,
            $typeValue,
            $resourceId,
            $actor,
        );

        $window = new DateTime(sprintf('-%d minutes', self::COALESCE_WINDOW_MINUTES));
        if ($latest instanceof BandSpaceActivity && $latest->creationDatetime >= $window) {
            return null;
        }

        return $this->record($bandSpace, $module, $typeValue, $resourceId, $actor, $payload);
    }
}
