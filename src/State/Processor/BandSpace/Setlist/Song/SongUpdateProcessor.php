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
use App\Security\BandSpace\SongWriteGuard;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\SongBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<SongResource, SongResource>
 */
readonly class SongUpdateProcessor implements ProcessorInterface
{
    /** Request key (snake_case, as the API names it) to the property it writes. */
    private const array WRITABLE_FIELDS = [
        'title' => 'title',
        'tempo' => 'tempo',
        'tonality' => 'tonality',
        'reference_duration' => 'referenceDuration',
        'notes' => 'notes',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SongWriteGuard $songWriteGuard,
        private SongRepository $songRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private SongBuilder $songBuilder,
        private Security $security,
        private RequestStack $requestStack,
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

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $song = $this->songRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$song instanceof Song) {
            throw new NotFoundHttpException('Chanson introuvable');
        }

        $this->songWriteGuard->assertWritable($song);

        // Only what the request sent (#1069). The DTO is the song as the provider read it with the sent
        // keys merged over it, so writing all of it would put back, from that snapshot, a field another
        // member changed meanwhile.
        $sent = $this->requestStack->getCurrentRequest()?->toArray() ?? [];
        $changed = false;
        foreach (self::WRITABLE_FIELDS as $key => $property) {
            if (array_key_exists($key, $sent) && $song->{$property} !== $data->{$property}) {
                $song->{$property} = $data->{$property};
                $changed = true;
            }
        }

        // A save that changes nothing is not an edit. Coalesced, because the drawer saves one field at
        // a time: filling in a song is one entry in the feed, not five.
        if ($changed) {
            $song->updateDatetime = new DateTime();
            $this->activityRecorder->recordCoalesced(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Setlist,
                type: BandSpaceSetlistActivityType::SongUpdated,
                resourceId: (string) $song->id,
                actor: $user,
                payload: ['title' => $song->title],
            );
        }

        $this->entityManager->flush();

        return $this->songBuilder->buildItem($song);
    }
}
