<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\Song\SongCreate;
use App\ApiResource\BandSpace\Setlist\Song\SongResource;
use App\Entity\BandSpace\Song;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\SongBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<SongCreate, SongResource>
 */
readonly class SongCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceActivityRecorder $activityRecorder,
        private SongBuilder $songBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param SongCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SongResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $song = new Song();
        $song->bandSpace = $bandSpace;
        $song->title = $data->title;
        $song->tempo = $data->tempo;
        $song->tonality = $data->tonality;
        $song->referenceDuration = $data->referenceDuration;
        $song->notes = $data->notes;

        $this->entityManager->persist($song);

        // Song::id is assigned at persist (CUSTOM UUID generator), so the
        // activity row can reference it before flush. Single flush commits
        // song + activity in one transaction.
        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::SongAdded,
            resourceId: (string) $song->id,
            actor: $user,
            payload: ['title' => $song->title],
        );

        $this->entityManager->flush();

        return $this->songBuilder->buildItem($song);
    }
}
