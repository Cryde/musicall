<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\Song\SongLyrics;
use App\ApiResource\BandSpace\Setlist\Song\SongLyricsTranspose;
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
use App\Service\BandSpace\Lyrics\LyricsTransposition;
use App\Service\Builder\BandSpace\SongLyricsBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<SongLyricsTranspose, SongLyrics>
 */
readonly class SongLyricsTransposeProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SongWriteGuard $songWriteGuard,
        private LyricsWriteGuard $lyricsWriteGuard,
        private SongRepository $songRepository,
        private LyricsRepository $lyricsRepository,
        private LyricsTransposition $transposition,
        private BandSpaceActivityRecorder $activityRecorder,
        private SongLyricsBuilder $lyricsBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param SongLyricsTranspose $data
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

        $this->transposition->apply($lyrics, $song, (int) $data->semitones);

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::SongUpdated,
            resourceId: (string) $song->id,
            actor: $user,
            payload: ['title' => $song->title],
        );

        $this->entityManager->flush();

        return $this->lyricsBuilder->build($song, $lyrics);
    }
}
