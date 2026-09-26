<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\Song\SongsArchive;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Repository\BandSpace\SongRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * All or nothing, like adding several: a song of another band refuses the request. One already in
 * the trash is left as it is, the same no-op archiving it alone is.
 *
 * @implements ProcessorInterface<SongsArchive, void>
 */
readonly class SongsArchiveProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SongRepository $songRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private Security $security,
    ) {
    }

    /**
     * @param SongsArchive $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $songIds = array_values(array_unique($data->songIds));
        $songs = $this->songRepository->findByIdsAndBandSpace($songIds, $bandSpace);
        if (count($songs) !== count($songIds)) {
            throw new UnprocessableEntityHttpException('Un des titres n\'est pas dans le répertoire de ce Band Space');
        }

        $archived = 0;
        $now = new DateTimeImmutable();
        foreach ($songs as $song) {
            if ($song->archiveDatetime === null) {
                $song->archiveDatetime = $now;
                ++$archived;
            }
        }

        if ($archived === 0) {
            return;
        }

        // One entry for the batch: fifty « a archivé la chanson » lines would bury the feed.
        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::SongsArchived,
            resourceId: (string) $bandSpace->id,
            actor: $user,
            payload: ['count' => $archived],
        );

        $this->entityManager->flush();
    }
}
