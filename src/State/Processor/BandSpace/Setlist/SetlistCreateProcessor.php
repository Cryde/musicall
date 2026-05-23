<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\SetlistCreate;
use App\ApiResource\BandSpace\Setlist\SetlistResource;
use App\Entity\BandSpace\Setlist;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\SetlistBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<SetlistCreate, SetlistResource>
 */
readonly class SetlistCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceActivityRecorder $activityRecorder,
        private SetlistBuilder $setlistBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param SetlistCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SetlistResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $setlist = new Setlist();
        $setlist->bandSpace = $bandSpace;
        $setlist->name = $data->name;

        $this->entityManager->persist($setlist);

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::SetlistCreated,
            resourceId: (string) $setlist->id,
            actor: $user,
            payload: ['name' => $setlist->name],
        );

        $this->entityManager->flush();

        return $this->setlistBuilder->buildItem($setlist);
    }
}
