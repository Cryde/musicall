<?php

declare(strict_types=1);

namespace App\Repository\Profile;

use App\Entity\Profile\ProfilePost;
use App\Entity\Profile\ProfilePostComment;
use App\Service\Profile\Wall\CommentCursor;
use App\Service\Profile\Wall\CommentOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * The ids of a page of top-level comments in `$order`, past `$before` (#1331), without those of the authors
     * given.
     *
     * @param list<string> $excludedAuthorIds
     *
     * @return list<string>
     */
    public function findRootIdsPage(ProfilePost $post, array $excludedAuthorIds, CommentOrder $order, ?CommentCursor $before, int $size): array
    {
        $query = $this->rootIdsQuery($post, $excludedAuthorIds, $order)->setMaxResults($size);
        $before?->applyAfter($query, 'c');

        return array_map('strval', array_column($query->getQuery()->getArrayResult(), 'id'));
    }

    /**
     * The ids of the top-level comments from the first in `$last`'s order down to `$last` included, at most `$max`.
     *
     * @param list<string> $excludedAuthorIds
     *
     * @return list<string>
     */
    public function findRootIdsDownTo(ProfilePost $post, array $excludedAuthorIds, CommentCursor $last, int $max): array
    {
        $query = $this->rootIdsQuery($post, $excludedAuthorIds, $last->order)->setMaxResults($max);
        $last->applyUpTo($query, 'c');

        return array_map('strval', array_column($query->getQuery()->getArrayResult(), 'id'));
    }

    /**
     * The top-level comments given, in that order, each followed by its replies oldest first, without the replies
     * of the authors given. Paged on the ids first: a fetch join with a limit would cut rows.
     *
     * @param list<string> $rootIds
     * @param list<string> $excludedAuthorIds
     *
     * @return list<ProfilePostComment>
     */
    public function findWithReplies(array $rootIds, array $excludedAuthorIds): array
    {
        if ($rootIds === []) {
            return [];
        }
        $query = $this->createQueryBuilder('c')
            ->addSelect('a', 'ap', 'app')
            ->innerJoin('c.author', 'a')
            ->innerJoin('a.profile', 'ap')
            ->leftJoin('a.profilePicture', 'app')
            ->where('c.id IN (:ids) OR c.parent IN (:ids)')
            ->setParameter('ids', $rootIds)
            ->orderBy('c.creationDatetime', 'ASC')
            ->addOrderBy('c.id', 'ASC');
        if ($excludedAuthorIds !== []) {
            $query->andWhere('a.id NOT IN (:excluded)')->setParameter('excluded', $excludedAuthorIds);
        }

        $roots = [];
        $repliesByRoot = [];
        /** @var ProfilePostComment $comment */
        foreach ($query->getQuery()->getResult() as $comment) {
            if ($comment->parent instanceof ProfilePostComment) {
                $repliesByRoot[(string) $comment->parent->id][] = $comment;
            } else {
                $roots[(string) $comment->id] = $comment;
            }
        }

        $thread = [];
        foreach ($rootIds as $rootId) {
            if (isset($roots[$rootId])) {
                $thread[] = $roots[$rootId];
                array_push($thread, ...$repliesByRoot[$rootId] ?? []);
            }
        }

        return $thread;
    }

    /**
     * @param list<string> $excludedAuthorIds
     */
    private function rootIdsQuery(ProfilePost $post, array $excludedAuthorIds, CommentOrder $order): QueryBuilder
    {
        $query = $this->createQueryBuilder('c')
            ->select('c.id')
            ->where('c.post = :post')
            ->andWhere('c.parent IS NULL')
            ->setParameter('post', $post);
        $order->orderBy($query, 'c');
        if ($excludedAuthorIds !== []) {
            $query->andWhere('c.author NOT IN (:excluded)')->setParameter('excluded', $excludedAuthorIds);
        }

        return $query;
    }

    /**
     * The stored like count (#1331) goes up by one. A bulk statement, so two likes at once both count: reading the
     * count and writing it back would lose one.
     */
    public function incrementLikeCount(ProfilePostComment $comment): void
    {
        $this->getEntityManager()
            ->createQuery('UPDATE App\Entity\Profile\ProfilePostComment c SET c.likeCount = c.likeCount + 1 WHERE c.id = :id')
            ->setParameter('id', $comment->id)
            ->execute();
    }

    /** The stored like count goes down by one, never below zero: the column is unsigned. */
    public function decrementLikeCount(ProfilePostComment $comment): void
    {
        $this->getEntityManager()
            ->createQuery('UPDATE App\Entity\Profile\ProfilePostComment c SET c.likeCount = c.likeCount - 1 WHERE c.id = :id AND c.likeCount > 0')
            ->setParameter('id', $comment->id)
            ->execute();
    }

    public function findOneOfPost(string $id, ProfilePost $post): ?ProfilePostComment
    {
        return $this->findOneBy(['id' => $id, 'post' => $post]);
    }

    /**
     * @param list<string> $postIds
     * @param list<string> $excludedAuthorIds
     *
     * @return array<string, int> comment count by post id, counted as {@see findWithReplies()} lists them
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
