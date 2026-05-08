<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceActivity;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

readonly class BandSpaceActivityRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function record(
        BandSpace $bandSpace,
        BandSpaceModule $module,
        string $type,
        UuidInterface|string|null $resourceId = null,
        ?User $actor = null,
        ?array $payload = null,
    ): BandSpaceActivity {
        $activity = new BandSpaceActivity();
        $activity->bandSpace = $bandSpace;
        $activity->module = $module;
        $activity->resourceId = is_string($resourceId) ? Uuid::fromString($resourceId) : $resourceId;
        $activity->actor = $actor;
        $activity->type = $type;
        $activity->payload = $payload;

        $this->entityManager->persist($activity);

        return $activity;
    }
}
