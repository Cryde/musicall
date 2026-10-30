<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\BandSpace\Lyrics;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Repository\BandSpace\LyricsRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * To the Corbeille, and off the song: the song can get new lyrics meanwhile, which is why a restore
 * brings these back unlinked rather than colliding with them.
 *
 * @implements ProcessorInterface<mixed, void>
 */
readonly class LyricsDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private LyricsRepository $lyricsRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
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

        if ($lyrics->archiveDatetime !== null) {
            // Already in the Corbeille - idempotent no-op.
            return;
        }

        $lyrics->archiveDatetime = new DateTimeImmutable();
        $lyrics->song = null;

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::LyricsArchived,
            resourceId: (string) $lyrics->id,
            actor: $user,
            payload: ['title' => $lyrics->title],
        );

        $this->entityManager->flush();
    }
}
