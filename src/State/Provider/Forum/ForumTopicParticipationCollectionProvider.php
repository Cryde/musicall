<?php

declare(strict_types=1);

namespace App\State\Provider\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Forum\ForumTopicParticipationResource;
use App\Entity\User;
use App\Repository\Forum\ForumTopicParticipationRepository;
use App\Service\Builder\Forum\ForumTopicParticipationBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<ForumTopicParticipationResource>
 */
readonly class ForumTopicParticipationCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ForumTopicParticipationRepository $repository,
        private ForumTopicParticipationBuilder    $builder,
        private Pagination                        $pagination,
        private Security                          $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $page = $this->pagination->getPage($context);
        $itemsPerPage = $this->pagination->getLimit($operation, $context);
        $offset = $this->pagination->getOffset($operation, $context);

        $qb = $this->repository->createQueryBuilderForUser($user, includeRemoved: false);
        $qb->setFirstResult($offset)->setMaxResults($itemsPerPage);

        $paginator = new Paginator($qb->getQuery());
        $totalItems = count($paginator);

        $dtos = $this->builder->buildFromEntities(iterator_to_array($paginator));

        return new TraversablePaginator(
            new \ArrayIterator($dtos),
            $page,
            $itemsPerPage,
            $totalItems
        );
    }
}
