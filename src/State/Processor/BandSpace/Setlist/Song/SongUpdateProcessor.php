<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\Song\SongResource;
use App\Entity\BandSpace\Song;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Repository\BandSpace\SongRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\SongBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<SongResource, SongResource>
 */
readonly class SongUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SongRepository $songRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private SongBuilder $songBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param SongResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SongResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $song = $this->songRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$song instanceof Song) {
            throw new NotFoundHttpException('Chanson introuvable');
        }

        $song->title = $data->title;
        $song->tempo = $data->tempo;
        $song->tonality = $data->tonality;
        $song->referenceDuration = $data->referenceDuration;
        $song->notes = $data->notes;
        $song->updateDatetime = new DateTime();

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::SongUpdated,
            resourceId: (string) $song->id,
            actor: $user,
            payload: ['title' => $song->title],
        );

        $this->entityManager->flush();

        return $this->songBuilder->buildItem($song);
    }
}
