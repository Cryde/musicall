<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\Filter\BandSpaceFileFilter;
use App\Repository\BandSpace\SetlistRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\File\BandSpaceFileBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<BandSpaceFileResource>
 */
readonly class BandSpaceSetlistFileCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private SetlistRepository $setlistRepository,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileBuilder $fileBuilder,
        private Security $security,
        private Pagination $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $setlist = $this->setlistRepository->findOneByIdAndBandSpace((string) $uriVariables['setlistId'], $bandSpace);
        if (!$setlist instanceof \App\Entity\BandSpace\Setlist) {
            throw new NotFoundHttpException('Setlist introuvable');
        }

        $page = $this->pagination->getPage($context);
        $itemsPerPage = $this->pagination->getLimit($operation, $context);
        $offset = $this->pagination->getOffset($operation, $context);

        $filter = new BandSpaceFileFilter(
            source: 'setlist',
            sourceId: (string) $setlist->id,
            limit: $itemsPerPage,
            offset: $offset,
        );

        $entities = $this->fileRepository->findByBandSpace($bandSpace, $filter);
        $totalItems = $this->fileRepository->countByBandSpace($bandSpace, $filter);

        $dtos = $this->fileBuilder->buildFromList($entities);

        return new TraversablePaginator(
            new \ArrayIterator($dtos),
            $page,
            $itemsPerPage,
            $totalItems,
        );
    }
}
