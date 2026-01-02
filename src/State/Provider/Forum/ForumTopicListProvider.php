<?php

declare(strict_types=1);

namespace App\State\Provider\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Forum\ForumTopic;
use App\Repository\Forum\ForumTopicRepository;
use App\Service\Builder\Forum\ForumTopicListBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @implements ProviderInterface<ForumTopic>
 */
readonly class ForumTopicListProvider implements ProviderInterface
{
    public function __construct(
        private ForumTopicRepository   $forumTopicRepository,
        private ForumTopicListBuilder  $forumTopicListBuilder,
        private Pagination             $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $forumSlug = $uriVariables['slug'];

        $page = $this->pagination->getPage($context);
        $itemsPerPage = $this->pagination->getLimit($operation, $context);
        $offset = $this->pagination->getOffset($operation, $context);

        $qb = $this->forumTopicRepository->createQueryBuilderByForumSlug($forumSlug);
        $qb->setFirstResult($offset)
           ->setMaxResults($itemsPerPage);

        $paginator = new Paginator($qb->getQuery());
        $totalItems = count($paginator);

        $topics = iterator_to_array($paginator);
        $dtos = $this->forumTopicListBuilder->buildFromEntities($topics);

        return new TraversablePaginator(
            new \ArrayIterator($dtos),
            $page,
            $itemsPerPage,
            $totalItems
        );
    }
}
