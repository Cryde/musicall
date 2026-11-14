<?php

declare(strict_types=1);

namespace App\Service\Profile\Wall;

use Doctrine\ORM\QueryBuilder;

/** How a post's top-level comments are paged (#1331). Replies stay in date order under their comment. */
enum CommentOrder: string
{
    case Recent = 'recent';
    case Oldest = 'oldest';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function orderBy(QueryBuilder $query, string $dateField, string $idField): QueryBuilder
    {
        $direction = match ($this) {
            self::Recent => 'DESC',
            self::Oldest => 'ASC',
        };

        return $query->orderBy($dateField, $direction)->addOrderBy($idField, $direction);
    }
}
