<?php

declare(strict_types=1);

namespace App\Repository\Profile;

use App\Entity\Profile\ProfilePostComment;
use App\Entity\Profile\ProfilePostCommentLike;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ProfilePostCommentLike>
 */
class ProfilePostCommentLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfilePostCommentLike::class);
    }

    /**
     * Raw SQL for its ON DUPLICATE KEY, as for a post's like: two quick taps both insert, and the unique key keeps
     * one row without a failed statement.
     *
     * @return bool whether a like was written: the duplicate path changes nothing and reports no affected row
     */
    public function add(ProfilePostComment $comment, User $user): bool
    {
        return $this->getEntityManager()->getConnection()->executeStatement(
            <<<'SQL'
                INSERT INTO profile_post_comment_like (id, comment_id, user_id, creation_datetime)
                VALUES (:id, :comment, :user, NOW())
                ON DUPLICATE KEY UPDATE id = id
                SQL,
            [
                'id' => Uuid::v7()->toRfc4122(),
                'comment' => (string) $comment->id,
                'user' => (string) $user->id,
            ],
        ) > 0;
    }

    /**
     * @return bool whether a like was there to remove
     */
    public function remove(ProfilePostComment $comment, User $user): bool
    {
        $deleted = $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\Profile\ProfilePostCommentLike l WHERE l.comment = :comment AND l.user = :user')
            ->setParameter('comment', $comment)
            ->setParameter('user', $user)
            ->execute();

        return is_int($deleted) && $deleted > 0;
    }

    /**
     * @param list<string> $commentIds
     * @param list<string> $excludedUserIds
     *
     * @return array<string, int> like count by comment id, without the users given
     */
    public function countByCommentIds(array $commentIds, array $excludedUserIds): array
    {
        if ($commentIds === []) {
            return [];
        }
        $query = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.comment) AS commentId', 'COUNT(l.id) AS total')
            ->where('l.comment IN (:comments)')
            ->setParameter('comments', $commentIds)
            ->groupBy('l.comment');
        if ($excludedUserIds !== []) {
            $query->andWhere('l.user NOT IN (:excluded)')->setParameter('excluded', $excludedUserIds);
        }

        $counts = [];
        foreach ($query->getQuery()->getArrayResult() as $row) {
            $counts[(string) $row['commentId']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @param list<string> $commentIds
     *
     * @return array<string, true> the ids, among those given, of the comments the user likes
     */
    public function findLikedCommentIds(array $commentIds, User $user): array
    {
        if ($commentIds === []) {
            return [];
        }
        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.comment) AS commentId')
            ->where('l.comment IN (:comments)')
            ->andWhere('l.user = :user')
            ->setParameter('comments', $commentIds)
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        $liked = [];
        foreach ($rows as $row) {
            $liked[(string) $row['commentId']] = true;
        }

        return $liked;
    }

    /**
     * A closed account's comment likes go, and the comments they liked count one less. Bulk, as nothing listens
     * to a like's removal: the counts move first, while the likes still say which comments.
     */
    public function deleteByUser(User $user): void
    {
        $this->getEntityManager()
            ->createQuery(
                'UPDATE App\Entity\Profile\ProfilePostComment c SET c.likeCount = c.likeCount - 1
                WHERE c.likeCount > 0 AND c.id IN (
                    SELECT IDENTITY(l.comment) FROM App\Entity\Profile\ProfilePostCommentLike l WHERE l.user = :user
                )',
            )
            ->setParameter('user', $user)
            ->execute();
        $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\Profile\ProfilePostCommentLike l WHERE l.user = :user')
            ->setParameter('user', $user)
            ->execute();
    }
}
