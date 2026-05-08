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
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\File\BandSpaceFileBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<BandSpaceFileResource>
 */
readonly class BandSpaceFileCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileBuilder $fileBuilder,
        private Security $security,
        private RequestStack $requestStack,
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

        $page = $this->pagination->getPage($context);
        $itemsPerPage = $this->pagination->getLimit($operation, $context);
        $offset = $this->pagination->getOffset($operation, $context);

        $filter = $this->buildFilter($itemsPerPage, $offset);

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

    private function buildFilter(int $limit, int $offset): BandSpaceFileFilter
    {
        $query = $this->requestStack->getCurrentRequest()?->query;

        return new BandSpaceFileFilter(
            folderId: $query?->getString('folder_id') ?: null,
            tagId: $query?->getString('tag_id') ?: null,
            source: $query?->getString('source') ?: null,
            query: $query?->getString('query') ?: null,
            mime: $query?->getString('mime') ?: null,
            uploaderId: $query?->getString('uploader_id') ?: null,
            sort: $query?->getString('sort') ?: 'date',
            order: $query?->getString('order') ?: 'desc',
            limit: $limit,
            offset: $offset,
        );
    }
}
