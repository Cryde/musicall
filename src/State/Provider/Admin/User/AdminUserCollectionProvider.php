<?php declare(strict_types=1);

namespace App\State\Provider\Admin\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\User\AdminUser;
use App\Repository\UserRepository;
use App\Service\Builder\Admin\User\AdminUserBuilder;
use ArrayIterator;

/**
 * @implements ProviderInterface<AdminUser>
 */
readonly class AdminUserCollectionProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private AdminUserBuilder $builder,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return TraversablePaginator<AdminUser>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $page = $this->pagination->getPage($context);
        $itemsPerPage = $this->pagination->getLimit($operation, $context);

        $search = $context['filters']['search'] ?? null;
        // No search term, no answer. This endpoint exists to look one person up, and returning
        // everyone by default would turn it into a way to page through the whole user base.
        if (!is_string($search) || trim($search) === '') {
            return new TraversablePaginator(new ArrayIterator([]), $page, $itemsPerPage, 0);
        }

        $paginator = $this->userRepository->searchForAdmin(
            trim($search),
            $this->pagination->getOffset($operation, $context),
            $itemsPerPage,
        );

        $resources = $this->builder->buildFromEntities(array_values(iterator_to_array($paginator)));

        return new TraversablePaginator(new ArrayIterator($resources), $page, $itemsPerPage, count($paginator));
    }
}
