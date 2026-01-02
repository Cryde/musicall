<?php

declare(strict_types=1);

namespace App\State\Provider\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Forum\TopicPost;
use App\Repository\Forum\ForumPostRepository;
use App\Service\Builder\Forum\TopicPostListBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @implements ProviderInterface<TopicPost>
 */
readonly class TopicPostListProvider implements ProviderInterface
{
    public function __construct(
        private ForumPostRepository   $forumPostRepository,
        private TopicPostListBuilder  $topicPostListBuilder,
        private Pagination            $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $topicSlug = $uriVariables['slug'];

        $page = $this->pagination->getPage($context);
        $itemsPerPage = $this->pagination->getLimit($operation, $context);
        $offset = $this->pagination->getOffset($operation, $context);

        $qb = $this->forumPostRepository->createQueryBuilderByTopicSlug($topicSlug);
        $qb->setFirstResult($offset)
           ->setMaxResults($itemsPerPage);

        $paginator = new Paginator($qb->getQuery());
        $totalItems = count($paginator);

        $posts = iterator_to_array($paginator);
        $dtos = $this->topicPostListBuilder->buildFromEntities($posts);

        return new TraversablePaginator(
            new \ArrayIterator($dtos),
            $page,
            $itemsPerPage,
            $totalItems
        );
    }
}
