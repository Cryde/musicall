<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\SetlistItemCreate;
use App\ApiResource\BandSpace\Setlist\SetlistItemResource;
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
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\SetlistItemBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<SetlistItemCreate, SetlistItemResource>
 */
readonly class SetlistItemCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SetlistRepository $setlistRepository,
        private SongRepository $songRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private SetlistItemBuilder $itemBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param SetlistItemCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SetlistItemResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $setlist = $this->setlistRepository->findOneByIdAndBandSpace((string) $uriVariables['setlistId'], $bandSpace);
        if (!$setlist instanceof Setlist) {
            throw new NotFoundHttpException('Setlist introuvable');
        }

        $song = null;
        if ($data->type === SetlistItemType::Song) {
            $song = $this->songRepository->findOneByIdAndBandSpace((string) $data->songId, $bandSpace);
            if (!$song instanceof Song) {
                throw new UnprocessableEntityHttpException('La chanson référencée n\'appartient pas à ce Band Space');
            }
        }

        // Type is non-null by Assert\NotNull on SetlistItemCreate; narrow for PHPStan.
        $type = $data->type;
        if ($type === null) {
            throw new UnprocessableEntityHttpException('Le type est requis');
        }

        $item = new SetlistItem();
        $item->setlist = $setlist;
        $item->type = $type;
        $item->song = $song;
        $item->label = $type === SetlistItemType::Song ? null : $data->label;
        $item->durationOverride = $data->durationOverride;
        $item->note = $data->note;
        $item->transition = $data->transition;
        $item->position = $this->nextPosition($setlist);

        $this->entityManager->persist($item);

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::SetlistItemAdded,
            resourceId: (string) $setlist->id,
            actor: $user,
            payload: [
                'item_id' => (string) $item->id,
                'type' => $item->type->value,
                'label' => $item->label ?? $song?->title,
            ],
        );

        $this->entityManager->flush();

        return $this->itemBuilder->buildItem($item);
    }

    private function nextPosition(Setlist $setlist): int
    {
        $max = -1;
        foreach ($setlist->items as $existing) {
            if ($existing->position > $max) {
                $max = $existing->position;
            }
        }

        return $max + 1;
    }
}
