<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\Lyrics\LyricsResource;
use App\ApiResource\BandSpace\Setlist\Lyrics\LyricsTranspose;
use App\Entity\BandSpace\Lyrics;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Repository\BandSpace\LyricsRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\LyricsWriteGuard;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\Lyrics\LyricsTransposition;
use App\Service\Builder\BandSpace\LyricsBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Unlinked lyrics have no key, so only their chords move.
 *
 * @implements ProcessorInterface<LyricsTranspose, LyricsResource>
 */
readonly class LyricsTransposeProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private LyricsWriteGuard $lyricsWriteGuard,
        private LyricsRepository $lyricsRepository,
        private LyricsTransposition $transposition,
        private BandSpaceActivityRecorder $activityRecorder,
        private LyricsBuilder $lyricsBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param LyricsTranspose $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LyricsResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $lyrics = $this->lyricsRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$lyrics instanceof Lyrics) {
            throw new NotFoundHttpException('band_space.lyrics.not_found');
        }

        $this->lyricsWriteGuard->assertWritable($lyrics);
        $this->lyricsWriteGuard->assertVersion($lyrics, $data->expectedVersion);

        if ($lyrics->content === null && $lyrics->song === null) {
            return $this->lyricsBuilder->buildItem($lyrics);
        }

        $this->transposition->apply($lyrics, $lyrics->song, (int) $data->semitones);

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::LyricsUpdated,
            resourceId: (string) $lyrics->id,
            actor: $user,
            payload: ['title' => $lyrics->title],
        );

        $this->entityManager->flush();

        return $this->lyricsBuilder->buildItem($lyrics);
    }
}
