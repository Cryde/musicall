<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\Song\SongLyrics;
use App\Entity\BandSpace\Lyrics;
use App\Entity\BandSpace\Song;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Repository\BandSpace\LyricsRepository;
use App\Repository\BandSpace\SongRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\LyricsWriteGuard;
use App\Security\BandSpace\SongWriteGuard;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\SongLyricsBuilder;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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
        private LyricsWriteGuard $lyricsWriteGuard,
        private SongRepository $songRepository,
        private LyricsRepository $lyricsRepository,
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
            throw new NotFoundHttpException('band_space.song.not_found');
        }

        $this->songWriteGuard->assertWritable($song);
        $lyrics = $this->lyricsRepository->findOneBySong($song);
        $this->lyricsWriteGuard->assertVersion($lyrics, $data->expectedLyricsVersion);

        $content = Lyrics::storedContent($data->lyrics);

        // A song with no lyrics only gets a row once there is something to store.
        if ($content !== $lyrics?->content) {
            $lyrics ??= $this->createLyrics($song);
            $lyrics->replaceContent($content);
            $song->updateDatetime = new DateTime();

            $this->activityRecorder->recordCoalesced(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Setlist,
                // The same entry as an edit from the « Paroles » screen, whichever one was used.
                type: BandSpaceSetlistActivityType::LyricsUpdated,
                resourceId: (string) $lyrics->id,
                actor: $user,
                payload: ['title' => $lyrics->title],
            );

            try {
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                // Another member's first save of these lyrics landed between our read and our insert.
                throw LyricsWriteGuard::conflict();
            }
        }

        return $this->lyricsBuilder->build($song, $lyrics);
    }

    private function createLyrics(Song $song): Lyrics
    {
        $lyrics = new Lyrics();
        $lyrics->bandSpace = $song->bandSpace;
        $lyrics->song = $song;
        $lyrics->title = $song->title;
        $this->entityManager->persist($lyrics);

        return $lyrics;
    }
}
