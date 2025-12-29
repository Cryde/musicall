<?php declare(strict_types=1);

namespace App\State\Provider\User\Course;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Entity\User;
use App\Service\Builder\User\Course\UserCourseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<\App\ApiResource\User\Course\UserCourse>
 */
class UserCourseCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserCourseBuilder $builder,
        private readonly Pagination $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $page = $this->pagination->getPage($context);
        $itemsPerPage = $this->pagination->getLimit($operation, $context);
        $offset = $this->pagination->getOffset($operation, $context);

        $filters = $context['filters'] ?? [];

        $qb = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Publication::class, 'p')
            ->join('p.subCategory', 'sc')
            ->where('p.author = :user')
            ->andWhere('sc.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', PublicationSubCategory::TYPE_COURSE);

        // Filter by status
        if (isset($filters['status']) && $filters['status'] !== '') {
            $status = (int) $filters['status'];
            if (in_array($status, Publication::ALL_STATUS, true)) {
                $qb->andWhere('p.status = :status')
                   ->setParameter('status', $status);
            }
        }

        // Filter by category
        if (isset($filters['category']) && $filters['category'] !== '') {
            $qb->andWhere('p.subCategory = :category')
               ->setParameter('category', (int) $filters['category']);
        }

        // Sorting
        $sortBy = $filters['sortBy'] ?? 'creation_datetime';
        $sortOrder = strtoupper($filters['sortOrder'] ?? 'desc');

        $sortOrder = in_array($sortOrder, ['ASC', 'DESC'], true) ? $sortOrder : 'DESC';

        $sortFieldMap = [
            'title' => 'p.title',
            'creation_datetime' => 'p.creationDatetime',
            'edition_datetime' => 'p.editionDatetime',
        ];

        $sortField = $sortFieldMap[$sortBy] ?? 'p.creationDatetime';
        $qb->orderBy($sortField, $sortOrder);

        // Pagination
        $qb->setFirstResult($offset)
           ->setMaxResults($itemsPerPage);

        $paginator = new Paginator($qb->getQuery());
        $totalItems = count($paginator);

        $publications = iterator_to_array($paginator);
        $dtos = $this->builder->buildFromEntities($publications);

        return new TraversablePaginator(
            new \ArrayIterator($dtos),
            $page,
            $itemsPerPage,
            $totalItems
        );
    }
}
