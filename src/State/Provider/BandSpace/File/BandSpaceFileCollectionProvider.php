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
    /** Reserved `folder_id` value asking for the root of the tree rather than the whole space. */
    public const string ROOT_FOLDER_ID = 'root';

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

        // `folder_id=root` rather than a second parameter: the caller is choosing one place to list, and
        // a reserved value keeps that one thing in one parameter. A real folder id is a uuid, so the
        // word cannot collide with one.
        $folderId = $query?->getString('folder_id') ?: null;
        $isRoot = $folderId === self::ROOT_FOLDER_ID;

        return new BandSpaceFileFilter(
            folderId: $isRoot ? null : $folderId,
            tagId: $query?->getString('tag_id') ?: null,
            source: $query?->getString('source') ?: null,
            sourceId: $query?->getString('task_id') ?: ($query?->getString('finance_entry_id') ?: null),
            query: $query?->getString('query') ?: null,
            mime: $query?->getString('mime') ?: null,
            uploaderId: $query?->getString('uploader_id') ?: null,
            sort: $query?->getString('sort') ?: 'date',
            order: $query?->getString('order') ?: 'desc',
            limit: $limit,
            offset: $offset,
            archivedOnly: $query?->getBoolean('archived') ?? false,
            rootOnly: $isRoot,
        );
    }
}
