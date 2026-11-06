<?php

declare(strict_types=1);

namespace App\Repository\Profile;

use App\Entity\Profile\ProfilePost;
use App\Entity\Profile\ProfilePostComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfilePostComment>
 */
class ProfilePostCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfilePostComment::class);
    }

    /**
     * A post's comments and replies, oldest first, without those of the authors given nor the replies under
     * their comments.
     *
     * @param list<string> $excludedAuthorIds
     *
     * @return list<ProfilePostComment>
     */
    public function findForPost(ProfilePost $post, array $excludedAuthorIds): array
    {
        $query = $this->createQueryBuilder('c')
            ->addSelect('a', 'ap', 'app')
            ->innerJoin('c.author', 'a')
            ->innerJoin('a.profile', 'ap')
            ->leftJoin('a.profilePicture', 'app')
            ->where('c.post = :post')
            ->setParameter('post', $post)
            ->orderBy('c.creationDatetime', 'ASC')
            ->addOrderBy('c.id', 'ASC');
        if ($excludedAuthorIds !== []) {
            $query->leftJoin('c.parent', 'parent')
                ->andWhere('a.id NOT IN (:excluded)')
                ->andWhere('parent.id IS NULL OR parent.author NOT IN (:excluded)')
                ->setParameter('excluded', $excludedAuthorIds);
        }

        /** @var list<ProfilePostComment> $comments */
        $comments = $query->getQuery()->getResult();

        return $comments;
    }

    public function findOneOfPost(string $id, ProfilePost $post): ?ProfilePostComment
    {
        return $this->findOneBy(['id' => $id, 'post' => $post]);
    }

    /**
     * @param list<string> $postIds
     * @param list<string> $excludedAuthorIds
     *
     * @return array<string, int> comment count by post id, counted as {@see findForPost()} lists them
     */
    public function countByPostIds(array $postIds, array $excludedAuthorIds): array
    {
        if ($postIds === []) {
            return [];
        }
        $query = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.post) AS postId', 'COUNT(c.id) AS total')
            ->where('c.post IN (:posts)')
            ->setParameter('posts', $postIds)
            ->groupBy('c.post');
        if ($excludedAuthorIds !== []) {
            $query->leftJoin('c.parent', 'parent')
                ->andWhere('c.author NOT IN (:excluded)')
                ->andWhere('parent.id IS NULL OR parent.author NOT IN (:excluded)')
                ->setParameter('excluded', $excludedAuthorIds);
        }

        $counts = [];
        foreach ($query->getQuery()->getArrayResult() as $row) {
            $counts[(string) $row['postId']] = (int) $row['total'];
        }

        return $counts;
    }
}
