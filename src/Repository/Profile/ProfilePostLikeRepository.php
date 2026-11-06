<?php

declare(strict_types=1);

namespace App\Repository\Profile;

use App\Entity\Profile\ProfilePost;
use App\Entity\Profile\ProfilePostLike;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ProfilePostLike>
 */
class ProfilePostLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfilePostLike::class);
    }

    /**
     * Raw SQL for its ON DUPLICATE KEY: two quick taps both insert, and the unique key keeps one row
     * without a failed statement, as for chat reactions.
     */
    public function add(ProfilePost $post, User $user): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            <<<'SQL'
                INSERT INTO profile_post_like (id, post_id, user_id, creation_datetime)
                VALUES (:id, :post, :user, NOW())
                ON DUPLICATE KEY UPDATE id = id
                SQL,
            [
                'id' => Uuid::v7()->toRfc4122(),
                'post' => (string) $post->id,
                'user' => (string) $user->id,
            ],
        );
    }

    /**
     * @return bool whether a like was there to remove
     */
    public function remove(ProfilePost $post, User $user): bool
    {
        $deleted = $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\Profile\ProfilePostLike l WHERE l.post = :post AND l.user = :user')
            ->setParameter('post', $post)
            ->setParameter('user', $user)
            ->execute();

        return is_int($deleted) && $deleted > 0;
    }

    /**
     * @param list<string> $postIds
     * @param list<string> $excludedUserIds
     *
     * @return array<string, int> like count by post id, counted as {@see findLikers()} lists them
     */
    public function countByPostIds(array $postIds, array $excludedUserIds = []): array
    {
        if ($postIds === []) {
            return [];
        }
        $query = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.post) AS postId', 'COUNT(l.id) AS total')
            ->where('l.post IN (:posts)')
            ->setParameter('posts', $postIds)
            ->groupBy('l.post');
        if ($excludedUserIds !== []) {
            $query->andWhere('l.user NOT IN (:excluded)')->setParameter('excluded', $excludedUserIds);
        }
        $rows = $query->getQuery()->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['postId']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @param list<string> $postIds
     *
     * @return array<string, true> the ids, among those given, of the posts the user likes
     */
    public function findLikedPostIds(array $postIds, User $user): array
    {
        if ($postIds === []) {
            return [];
        }
        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.post) AS postId')
            ->where('l.post IN (:posts)')
            ->andWhere('l.user = :user')
            ->setParameter('posts', $postIds)
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        $liked = [];
        foreach ($rows as $row) {
            $liked[(string) $row['postId']] = true;
        }

        return $liked;
    }

    /**
     * Who liked a post, newest first, without the users given.
     *
     * @param list<string> $excludedUserIds
     *
     * @return list<User>
     */
    public function findLikers(ProfilePost $post, array $excludedUserIds): array
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('u', 'p', 'pp')
            ->from(User::class, 'u')
            ->innerJoin(ProfilePostLike::class, 'l', 'WITH', 'l.user = u')
            ->innerJoin('u.profile', 'p')
            ->leftJoin('u.profilePicture', 'pp')
            ->where('l.post = :post')
            ->setParameter('post', $post)
            ->orderBy('l.creationDatetime', 'DESC')
            ->addOrderBy('l.id', 'DESC');
        if ($excludedUserIds !== []) {
            $query->andWhere('u.id NOT IN (:excluded)')->setParameter('excluded', $excludedUserIds);
        }

        /** @var list<User> $users */
        $users = $query->getQuery()->getResult();

        return $users;
    }

    /**
     * A closed account's likes go: there is no one left behind them. Bulk, as nothing listens to a like's removal.
     */
    public function deleteByUser(User $user): void
    {
        $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\Profile\ProfilePostLike l WHERE l.user = :user')
            ->setParameter('user', $user)
            ->execute();
    }
}
