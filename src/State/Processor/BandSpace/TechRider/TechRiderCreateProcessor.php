<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\TechRider\TechRiderCreate;
use App\ApiResource\BandSpace\TechRider\TechRiderResource;
use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderItem;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceRiderActivityType;
use App\Enum\BandSpace\TechRiderDefaultItem;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\TechRider\TechRiderBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<TechRiderCreate, TechRiderResource>
 */
readonly class TechRiderCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceActivityRecorder $activityRecorder,
        private TechRiderBuilder $techRiderBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param TechRiderCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TechRiderResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $techRider = new TechRider();
        $techRider->bandSpace = $bandSpace;
        $techRider->createdBy = $user;
        $techRider->name = $data->name;

        $this->entityManager->persist($techRider);
        $this->seedDefaultItems($techRider);

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Rider,
            type: BandSpaceRiderActivityType::RiderCreated,
            resourceId: (string) $techRider->id,
            actor: $user,
            payload: ['name' => $techRider->name],
        );

        $this->entityManager->flush();

        return $this->techRiderBuilder->buildItem($techRider);
    }

    /**
     * A new rider opens on a prompt rather than a blank page. These are ordinary rows from
     * the moment they exist: rename, reorder or delete them freely. No activity is recorded
     * for them, they are part of creating the rider rather than seven separate edits.
     */
    private function seedDefaultItems(TechRider $techRider): void
    {
        foreach (TechRiderDefaultItem::cases() as $position => $default) {
            $item = new TechRiderItem();
            $item->techRider = $techRider;
            $item->title = $default->title();
            $item->position = $position;

            $techRider->items->add($item);
            $this->entityManager->persist($item);
        }
    }
}
