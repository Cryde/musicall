<?php

declare(strict_types=1);

namespace App\Service\Profile\Wall;

use Doctrine\ORM\QueryBuilder;

/** How a post's top-level comments are paged (#1331). Replies stay in date order under their comment. */
enum CommentOrder: string
{
    case Recent = 'recent';
    case Oldest = 'oldest';
    case Liked = 'liked';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Orders the top-level comments of `$alias`, a ProfilePostComment. */
    public function orderBy(QueryBuilder $query, string $alias): QueryBuilder
    {
        if ($this === self::Liked) {
            // The stored count holds every like, while each comment shows its count without the people the viewer
            // blocks or is blocked by: a viewer may now and then read « 3 » ranked above « 4 ».
            $query->orderBy($alias . '.likeCount', 'DESC')->addOrderBy($alias . '.creationDatetime', 'DESC');
        } else {
            $query->orderBy($alias . '.creationDatetime', $this === self::Oldest ? 'ASC' : 'DESC');
        }

        return $query->addOrderBy($alias . '.id', $this === self::Oldest ? 'ASC' : 'DESC');
    }
}
