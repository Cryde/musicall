<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\Song\SongLyrics;
use App\Entity\BandSpace\Song;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Repository\BandSpace\SongRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\SongWriteGuard;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\SongLyricsBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<SongLyrics, SongLyrics>
 */
readonly class SongLyricsUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SongWriteGuard $songWriteGuard,
        private SongRepository $songRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private SongLyricsBuilder $lyricsBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param SongLyrics $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SongLyrics
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $song = $this->songRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$song instanceof Song) {
            throw new NotFoundHttpException('Chanson introuvable');
        }

        $this->songWriteGuard->assertWritable($song);
        $this->songWriteGuard->assertLyricsVersion($song, $data->expectedLyricsVersion);

        $lyrics = trim((string) $data->lyrics) === '' ? null : $data->lyrics;

        // Only a real change bumps the revision: re-saving the same text loses nobody any work.
        if ($lyrics !== $song->lyrics) {
            $song->lyrics = $lyrics;
            ++$song->lyricsVersion;
            $song->updateDatetime = new DateTime();

            $this->activityRecorder->recordCoalesced(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Setlist,
                type: BandSpaceSetlistActivityType::SongUpdated,
                resourceId: (string) $song->id,
                actor: $user,
                payload: ['title' => $song->title],
            );

            $this->entityManager->flush();
        }

        return $this->lyricsBuilder->build($song);
    }
}
