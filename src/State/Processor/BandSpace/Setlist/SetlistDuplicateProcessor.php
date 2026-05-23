<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\SetlistResource;
use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\SetlistItem;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Repository\BandSpace\SetlistRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\SetlistBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, SetlistResource>
 */
readonly class SetlistDuplicateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SetlistRepository $setlistRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private SetlistBuilder $setlistBuilder,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SetlistResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $source = $this->setlistRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$source instanceof Setlist) {
            throw new NotFoundHttpException('Setlist introuvable');
        }

        $copy = new Setlist();
        $copy->bandSpace = $bandSpace;
        $copy->name = $source->name . ' (copie)';

        $this->entityManager->persist($copy);

        foreach ($source->items as $sourceItem) {
            $itemCopy = new SetlistItem();
            $itemCopy->setlist = $copy;
            $itemCopy->type = $sourceItem->type;
            $itemCopy->song = $sourceItem->song;
            $itemCopy->label = $sourceItem->label;
            $itemCopy->durationOverride = $sourceItem->durationOverride;
            $itemCopy->note = $sourceItem->note;
            $itemCopy->transition = $sourceItem->transition;
            $itemCopy->position = $sourceItem->position;

            $this->entityManager->persist($itemCopy);
            $copy->items->add($itemCopy);
        }

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::SetlistDuplicated,
            resourceId: (string) $copy->id,
            actor: $user,
            payload: ['name' => $copy->name, 'source_id' => (string) $source->id],
        );

        $this->entityManager->flush();

        return $this->setlistBuilder->buildItem($copy);
    }
}
