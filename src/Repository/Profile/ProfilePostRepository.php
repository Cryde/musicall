<?php

declare(strict_types=1);

namespace App\Repository\Profile;

use App\Entity\Profile\ProfilePost;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfilePost>
 */
class ProfilePostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfilePost::class);
    }

    /**
     * The unpinned posts of a wall, newest first, after `$before` when given. Keyset on (creation, id), as two
     * posts can share a second.
     *
     * @return list<ProfilePost>
     */
    public function findWallPage(User $author, ?ProfilePost $before, int $limit): array
    {
        // Paged on the ids alone: a fetch join with a limit would cut a post's images.
        $page = $this->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.author = :author')
            ->andWhere('p.isPinned = false')
            ->setParameter('author', $author)
            ->orderBy('p.creationDatetime', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit);
        if ($before instanceof ProfilePost) {
            $page->andWhere('p.creationDatetime < :beforeDate OR (p.creationDatetime = :beforeDate AND p.id < :beforeId)')
                ->setParameter('beforeDate', $before->creationDatetime)
                ->setParameter('beforeId', $before->id);
        }
        $ids = array_column($page->getQuery()->getArrayResult(), 'id');
        if ($ids === []) {
            return [];
        }

        /** @var list<ProfilePost> $posts */
        $posts = $this->createQueryBuilder('p')
            ->addSelect('i')
            ->leftJoin('p.images', 'i')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('p.creationDatetime', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $posts;
    }

    public function findPinned(User $author): ?ProfilePost
    {
        return $this->findOneBy(['author' => $author, 'isPinned' => true]);
    }

    public function findOneOfAuthor(string $id, User $author): ?ProfilePost
    {
        return $this->findOneBy(['id' => $id, 'author' => $author]);
    }

    /**
     * One pinned post per member: unpinning in bulk is one statement, and no lifecycle event depends on it.
     */
    public function unpinAllOf(User $author): void
    {
        $this->getEntityManager()
            ->createQuery('UPDATE App\Entity\Profile\ProfilePost p SET p.isPinned = false WHERE p.author = :author AND p.isPinned = true')
            ->setParameter('author', $author)
            ->execute();
    }

    /**
     * @return list<ProfilePost>
     */
    public function findByAuthor(User $author): array
    {
        /** @var list<ProfilePost> $posts */
        $posts = $this->findBy(['author' => $author]);

        return $posts;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<ProfilePost> with their authors, in no particular order
     */
    public function findWithAuthorsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var list<ProfilePost> $posts */
        $posts = $this->createQueryBuilder('p')
            ->addSelect('a')
            ->innerJoin('p.author', 'a')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        return $posts;
    }
}
