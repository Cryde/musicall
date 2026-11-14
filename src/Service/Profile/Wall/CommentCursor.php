<?php

declare(strict_types=1);

namespace App\Service\Profile\Wall;

use App\Entity\Profile\ProfilePostComment;
use Doctrine\ORM\QueryBuilder;

/**
 * A top-level comment's place in its post's thread, `{order}_{unix timestamp}_{uuid}` (#1331). It names the order
 * it was made for, so a cursor from one order is never read in another.
 */
final readonly class CommentCursor
{
    public const string PATTERN = '/^recent_' . DatedPosition::FORMAT . '\z/';

    private function __construct(
        public CommentOrder $order,
        private DatedPosition $position,
    ) {
    }

    public static function of(CommentOrder $order, ProfilePostComment $comment): self
    {
        return new self($order, DatedPosition::of($comment->creationDatetime, (string) $comment->id));
    }

    /** Null for anything the pattern refuses: the query parameter's own validation has answered 422 by then. */
    public static function parse(string $value): ?self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            return null;
        }
        [$order, $position] = explode('_', $value, 2);
        $position = DatedPosition::parse($position);

        return $position instanceof DatedPosition ? new self(CommentOrder::from($order), $position) : null;
    }

    public function toString(): string
    {
        return sprintf('%s_%s', $this->order->value, $this->position->toString());
    }

    /** Keeps the top-level comments a later page holds. */
    public function applyAfter(QueryBuilder $query, string $dateField, string $idField): QueryBuilder
    {
        return $this->position->applyAfterNewestFirst($query, $dateField, $idField);
    }

    /** Keeps this comment and those an earlier page holds. */
    public function applyUpTo(QueryBuilder $query, string $dateField, string $idField): QueryBuilder
    {
        return $this->position->applyUpToNewestFirst($query, $dateField, $idField);
    }
}
