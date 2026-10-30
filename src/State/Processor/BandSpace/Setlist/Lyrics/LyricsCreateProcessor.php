<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\Lyrics\LyricsCreate;
use App\ApiResource\BandSpace\Setlist\Lyrics\LyricsResource;
use App\Entity\BandSpace\Lyrics;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\Lyrics\LyricsSongLinker;
use App\Service\Builder\BandSpace\LyricsBuilder;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<LyricsCreate, LyricsResource>
 */
readonly class LyricsCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private LyricsSongLinker $songLinker,
        private BandSpaceActivityRecorder $activityRecorder,
        private LyricsBuilder $lyricsBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param LyricsCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LyricsResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $lyrics = new Lyrics();
        $lyrics->bandSpace = $bandSpace;
        $lyrics->title = $data->title;
        $lyrics->content = Lyrics::storedContent($data->content);
        if ($data->songId !== null) {
            $this->songLinker->link($lyrics, $data->songId);
        }

        $this->entityManager->persist($lyrics);

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::LyricsAdded,
            resourceId: (string) $lyrics->id,
            actor: $user,
            payload: ['title' => $lyrics->title, 'song_title' => $lyrics->song?->title],
        );

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw LyricsSongLinker::songHasLyrics();
        }

        return $this->lyricsBuilder->buildItem($lyrics);
    }
}
