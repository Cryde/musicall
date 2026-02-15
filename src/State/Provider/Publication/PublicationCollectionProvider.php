<?php

declare(strict_types=1);

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Publication\PublicationListItem;
use App\Repository\PublicationRepository;
use App\Service\Builder\Publication\PublicationListItemBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @implements ProviderInterface<PublicationListItem>
 */
readonly class PublicationCollectionProvider implements ProviderInterface
{
    public function __construct(
        private PublicationRepository       $publicationRepository,
        private PublicationListItemBuilder  $publicationListItemBuilder,
        private Pagination                  $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $page = $this->pagination->getPage($context);
        $itemsPerPage = $this->pagination->getLimit($operation, $context);
        $offset = $this->pagination->getOffset($operation, $context);

        $filters = $context['filters'] ?? [];
        $subCategorySlug = $filters['sub_category.slug'] ?? null;
        $subCategoryType = isset($filters['sub_category.type']) ? (int) $filters['sub_category.type'] : null;
        $orderDirection = $filters['order']['publication_datetime'] ?? 'desc';

        $qb = $this->publicationRepository->createCollectionQueryBuilder($subCategorySlug, $subCategoryType, $orderDirection);
        $qb->setFirstResult($offset)
           ->setMaxResults($itemsPerPage);

        $paginator = new Paginator($qb->getQuery());
        $totalItems = count($paginator);

        $publications = iterator_to_array($paginator);
        $dtos = $this->publicationListItemBuilder->buildFromEntities($publications);

        return new TraversablePaginator(
            new \ArrayIterator($dtos),
            $page,
            $itemsPerPage,
            $totalItems,
        );
    }
}
