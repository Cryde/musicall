<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\SetlistItemsBulkCreate;
use App\ApiResource\BandSpace\Setlist\SetlistResource;
use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\SetlistItem;
use App\Entity\BandSpace\Song;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Enum\BandSpace\SetlistItemType;
use App\Repository\BandSpace\SetlistRepository;
use App\Repository\BandSpace\SongRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\SetlistWriteGuard;
use App\Security\BandSpace\SongWriteGuard;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\Setlist\SetlistRunningOrder;
use App\Service\Builder\BandSpace\SetlistBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<SetlistItemsBulkCreate, SetlistResource>
 */
readonly class SetlistItemsBulkCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SetlistWriteGuard $setlistWriteGuard,
        private SongWriteGuard $songWriteGuard,
        private SetlistRepository $setlistRepository,
        private SongRepository $songRepository,
        private SetlistRunningOrder $runningOrder,
        private BandSpaceActivityRecorder $activityRecorder,
        private SetlistBuilder $setlistBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param SetlistItemsBulkCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SetlistResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $setlist = $this->setlistRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$setlist instanceof Setlist) {
            throw new NotFoundHttpException('Setlist introuvable');
        }

        $this->setlistWriteGuard->assertWritable($setlist);

        $songsById = [];
        foreach ($this->songRepository->findByIdsAndBandSpace(array_values(array_unique($data->songIds)), $bandSpace) as $song) {
            $songsById[(string) $song->id] = $song;
        }

        // All or nothing: a set with half the songs asked for is worse than a clear refusal. An archived
        // song gets the same answer as adding it alone.
        $items = [];
        foreach ($data->songIds as $songId) {
            $song = $songsById[$songId] ?? null;
            if (!$song instanceof Song) {
                throw new UnprocessableEntityHttpException('Un des titres n\'est pas dans le répertoire de ce Band Space');
            }
            $this->songWriteGuard->assertWritable($song);
            $item = new SetlistItem();
            $item->type = SetlistItemType::Song;
            $item->song = $song;
            $items[] = $item;
        }

        $this->runningOrder->insert($setlist, $items, $data->position);
        foreach ($items as $item) {
            $this->entityManager->persist($item);
        }

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::SetlistItemsAdded,
            resourceId: (string) $setlist->id,
            actor: $user,
            payload: ['count' => count($items), 'name' => $setlist->name],
        );

        $setlist->updateDatetime = new \DateTime();
        $this->entityManager->flush();

        return $this->setlistBuilder->buildItem($setlist);
    }
}
