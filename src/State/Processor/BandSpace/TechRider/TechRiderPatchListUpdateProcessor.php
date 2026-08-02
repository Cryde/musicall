<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\TechRider\TechRiderItemResource;
use App\ApiResource\BandSpace\TechRider\TechRiderPatchList;
use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderItem;
use App\Entity\User;
use App\Procedure\BandSpace\TechRiderPatchListReplaceProcedure;
use App\Repository\BandSpace\TechRiderItemRepository;
use App\Repository\BandSpace\TechRiderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\TechRiderWriteGuard;
use App\Service\Builder\BandSpace\TechRider\TechRiderItemBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<TechRiderPatchList, TechRiderItemResource>
 */
readonly class TechRiderPatchListUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TechRiderWriteGuard $writeGuard,
        private TechRiderRepository $techRiderRepository,
        private TechRiderItemRepository $itemRepository,
        private TechRiderPatchListReplaceProcedure $replaceProcedure,
        private TechRiderItemBuilder $itemBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param TechRiderPatchList $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TechRiderItemResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $techRider = $this->techRiderRepository->findOneByIdAndBandSpace((string) $uriVariables['riderId'], $bandSpace);
        if (!$techRider instanceof TechRider) {
            throw new NotFoundHttpException('Tech rider introuvable');
        }

        $this->writeGuard->assertWritable($techRider);

        $item = $this->itemRepository->findOneByIdAndRider((string) $uriVariables['itemId'], $techRider);
        if (!$item instanceof TechRiderItem) {
            throw new NotFoundHttpException('Élément introuvable');
        }

        if (!$item->type->storesRelationalRows()) {
            throw new UnprocessableEntityHttpException(
                'Seul un élément de type patch list peut contenir une patch list',
            );
        }

        // Everything the save consists of, including the activity row and the timestamp, happens
        // inside the procedure's transaction. Nothing is written here afterwards.
        $this->replaceProcedure->replace($item, $data->inputs, $data->outputs, $user);

        return $this->itemBuilder->buildItem($item);
    }
}
