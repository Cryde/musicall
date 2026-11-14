<?php

declare(strict_types=1);

namespace App\Service\Profile\Wall;

use App\Entity\Profile\ProfilePostComment;
use Doctrine\ORM\QueryBuilder;

/**
 * A top-level comment's place in its post's thread (#1331): `{order}_{unix timestamp}_{uuid}`, and
 * `liked_{like count}_{unix timestamp}_{uuid}` in the most liked order. It names the order it was made for, so a
 * cursor from one order is never read in another.
 */
final readonly class CommentCursor
{
    /** Every {@see CommentOrder}, which CommentCursorTest checks. A like count fits an unsigned int. */
    public const string PATTERN = '/^(?:recent|oldest|liked_\d{1,10})_' . DatedPosition::FORMAT . '\z/';

    private function __construct(
        public CommentOrder $order,
        private ?int $likeCount,
        private DatedPosition $position,
    ) {
    }

    public static function of(CommentOrder $order, ProfilePostComment $comment): self
    {
        return new self(
            $order,
            $order === CommentOrder::Liked ? $comment->likeCount : null,
            DatedPosition::of($comment->creationDatetime, (string) $comment->id),
        );
    }

    /** Null for anything the pattern refuses: the query parameter's own validation has answered 422 by then. */
    public static function parse(string $value): ?self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            return null;
        }
        [$order, $rest] = explode('_', $value, 2);
        $order = CommentOrder::from($order);
        $likeCount = null;
        if ($order === CommentOrder::Liked) {
            [$likeCount, $rest] = explode('_', $rest, 2);
            $likeCount = (int) $likeCount;
        }
        $position = DatedPosition::parse($rest);

        return $position instanceof DatedPosition ? new self($order, $likeCount, $position) : null;
    }

    public function toString(): string
    {
        return $this->likeCount === null
            ? sprintf('%s_%s', $this->order->value, $this->position->toString())
            : sprintf('%s_%d_%s', $this->order->value, $this->likeCount, $this->position->toString());
    }

    /** Keeps the top-level comments of `$alias`, a ProfilePostComment, that a later page holds. */
    public function applyAfter(QueryBuilder $query, string $alias): QueryBuilder
    {
        [$date, $id] = [$alias . '.creationDatetime', $alias . '.id'];

        return $query->andWhere(match ($this->order) {
            CommentOrder::Recent => $this->position->afterNewestFirst($query, $date, $id),
            CommentOrder::Oldest => $this->position->afterOldestFirst($query, $date, $id),
            CommentOrder::Liked => $this->likedBound($query, $alias, '<', $this->position->afterNewestFirst($query, $date, $id)),
        });
    }

    /** Keeps this comment and those an earlier page holds. */
    public function applyUpTo(QueryBuilder $query, string $alias): QueryBuilder
    {
        [$date, $id] = [$alias . '.creationDatetime', $alias . '.id'];

        return $query->andWhere(match ($this->order) {
            CommentOrder::Recent => $this->position->upToNewestFirst($query, $date, $id),
            CommentOrder::Oldest => $this->position->upToOldestFirst($query, $date, $id),
            CommentOrder::Liked => $this->likedBound($query, $alias, '>', $this->position->upToNewestFirst($query, $date, $id)),
        });
    }

    /** The count decides, then the date and id among equal counts. */
    private function likedBound(QueryBuilder $query, string $alias, string $countOperator, string $withinCount): string
    {
        $query->setParameter('cursorLikeCount', $this->likeCount);

        return sprintf('%1$s.likeCount %2$s :cursorLikeCount OR (%1$s.likeCount = :cursorLikeCount AND (%3$s))', $alias, $countOperator, $withinCount);
    }
}
