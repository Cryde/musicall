<?php declare(strict_types=1);

namespace App\Repository\Forum;

use App\Entity\Forum\ForumTopic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumTopic>
 */
class ForumTopicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumTopic::class);
    }

    public function createQueryBuilderByForumSlug(string $forumSlug): QueryBuilder
    {
        return $this->createQueryBuilder('ft')
            ->innerJoin('ft.forum', 'f')
            ->leftJoin('ft.lastPost', 'lp')
            ->leftJoin('lp.creator', 'lpc')
            ->innerJoin('ft.author', 'a')
            ->addSelect('lp', 'lpc', 'a')
            ->where('f.slug = :forumSlug')
            ->setParameter('forumSlug', $forumSlug)
            ->orderBy('ft.type', 'DESC')
            ->addOrderBy('ft.creationDatetime', 'DESC');
    }
}
