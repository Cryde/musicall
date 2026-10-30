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
use App\Security\BandSpace\LyricsWriteGuard;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\LyricsBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Only what the request sent is written (#1069). The version is required with the content only:
 * a rename cannot wipe anyone's text, so it is not held to the revision.
 *
 * @implements ProcessorInterface<LyricsResource, LyricsResource>
 */
readonly class LyricsUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private LyricsWriteGuard $lyricsWriteGuard,
        private LyricsRepository $lyricsRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private LyricsBuilder $lyricsBuilder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param LyricsResource $data
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

        $sent = $this->requestStack->getCurrentRequest()?->toArray() ?? [];
        $changed = false;

        if (array_key_exists('content', $sent)) {
            $this->lyricsWriteGuard->assertVersion($lyrics, $data->expectedVersion);
            if ($lyrics->replaceContent($data->content)) {
                if ($lyrics->song !== null) {
                    $lyrics->song->updateDatetime = new DateTime();
                }
                $changed = true;
            }
        }

        if (array_key_exists('title', $sent) && $data->title !== $lyrics->title) {
            $lyrics->title = $data->title;
            $changed = true;
        }

        if ($changed) {
            $lyrics->updateDatetime = new DateTime();
            // Coalesced: the editor autosaves, so one writing session is one line in the feed.
            $this->activityRecorder->recordCoalesced(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Setlist,
                type: BandSpaceSetlistActivityType::LyricsUpdated,
                resourceId: (string) $lyrics->id,
                actor: $user,
                payload: ['title' => $lyrics->title],
            );
            $this->entityManager->flush();
        }

        return $this->lyricsBuilder->buildItem($lyrics);
    }
}
