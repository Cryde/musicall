<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\SetlistItemsCopy;
use App\ApiResource\BandSpace\Setlist\SetlistResource;
use App\Entity\BandSpace\Setlist;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Repository\BandSpace\SetlistRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\SetlistWriteGuard;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\Setlist\SetlistItemCopier;
use App\Service\BandSpace\Setlist\SetlistRunningOrder;
use App\Service\Builder\BandSpace\SetlistBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<SetlistItemsCopy, SetlistResource>
 */
readonly class SetlistItemsCopyProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SetlistWriteGuard $setlistWriteGuard,
        private SetlistRepository $setlistRepository,
        private SetlistItemCopier $itemCopier,
        private SetlistRunningOrder $runningOrder,
        private BandSpaceActivityRecorder $activityRecorder,
        private SetlistBuilder $setlistBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param SetlistItemsCopy $data
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

        // An archived setlist is still a fine starting point, as it is for « Dupliquer ».
        $source = $this->setlistRepository->findOneByIdAndBandSpace((string) $data->fromSetlistId, $bandSpace);
        if (!$source instanceof Setlist) {
            throw new UnprocessableEntityHttpException('La setlist à copier n\'appartient pas à ce Band Space');
        }
        if ($source->id === $setlist->id) {
            throw new UnprocessableEntityHttpException('Choisissez une autre setlist que celle-ci');
        }

        $copies = $this->itemCopier->copyItems($source);
        if ($copies === []) {
            throw new UnprocessableEntityHttpException('La setlist à copier est vide');
        }
        $this->runningOrder->insert($setlist, $copies);
        foreach ($copies as $copy) {
            $this->entityManager->persist($copy);
        }

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::SetlistItemsCopied,
            resourceId: (string) $setlist->id,
            actor: $user,
            payload: ['count' => count($copies), 'name' => $setlist->name, 'source_name' => $source->name],
        );

        $setlist->updateDatetime = new \DateTime();
        $this->entityManager->flush();

        return $this->setlistBuilder->buildItem($setlist);
    }
}
