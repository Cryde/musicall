<?php declare(strict_types=1);

namespace App\Repository\Forum;

use App\Entity\Forum\ForumPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumPost>
 */
class ForumPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumPost::class);
    }

    public function createQueryBuilderByTopicSlug(string $topicSlug): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->join('p.topic', 't')
            ->join('p.creator', 'c')
            ->leftJoin('c.profilePicture', 'pp')
            ->addSelect('c', 'pp')
            ->where('t.slug = :slug')
            ->setParameter('slug', $topicSlug)
            ->orderBy('p.creationDatetime', 'ASC');
    }
}
