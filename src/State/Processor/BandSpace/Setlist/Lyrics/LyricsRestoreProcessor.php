<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\Lyrics\LyricsResource;
use App\Entity\BandSpace\Lyrics;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Repository\BandSpace\LyricsRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\LyricsBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Back from the Corbeille, unlinked: archiving took them off their song.
 *
 * @implements ProcessorInterface<mixed, LyricsResource>
 */
readonly class LyricsRestoreProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private LyricsRepository $lyricsRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private LyricsBuilder $lyricsBuilder,
        private Security $security,
    ) {
    }

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

        if ($lyrics->archiveDatetime === null) {
            throw new ConflictHttpException('band_space.lyrics.not_in_trash');
        }

        $lyrics->archiveDatetime = null;

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::LyricsRestored,
            resourceId: (string) $lyrics->id,
            actor: $user,
            payload: ['title' => $lyrics->title],
        );

        $this->entityManager->flush();

        return $this->lyricsBuilder->buildItem($lyrics);
    }
}
