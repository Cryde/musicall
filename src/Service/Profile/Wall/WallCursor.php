<?php

declare(strict_types=1);

namespace App\Service\Profile\Wall;

use Doctrine\ORM\QueryBuilder;

/**
 * A place in a wall, `{kind}_{unix timestamp}_{id}` (#1316): the wall reads several tables, so the place is a
 * position in their merged order, not a row, and a row deleted since still leaves the next page where it was.
 * The order is the date, newest first, then the kind's rank, then the id within a kind.
 */
final readonly class WallCursor
{
    /**
     * A publication carries an int id, every other kind a uuid. Anchored with \z: `$` would also take a trailing
     * newline.
     */
    public const string PATTERN = '/^(?:publication_\d{1,11}_\d{1,18}|(?:post|announce|musician_media|teacher_media)_\d{1,11}_[0-9a-f-]{36})\z/';

    private function __construct(
        public WallItemKind $kind,
        public int $timestamp,
        public string $id,
    ) {
    }

    public static function at(WallItemKind $kind, \DateTimeInterface $date, string $id): self
    {
        return new self($kind, $date->getTimestamp(), $id);
    }

    /** Null for anything the pattern refuses: the query parameter's own validation has answered 422 by then. */
    public static function parse(string $value): ?self
    {
        if (preg_match(self::PATTERN, $value) !== 1 || preg_match('/^([a-z_]+)_(\d+)_(.+)\z/', $value, $parts) !== 1) {
            return null;
        }

        return new self(WallItemKind::from($parts[1]), (int) $parts[2], $parts[3]);
    }

    public function toString(): string
    {
        return sprintf('%s_%d_%s', $this->kind->value, $this->timestamp, $this->id);
    }

    /**
     * Keeps the rows of `$kind` that come after this place: a kind ranked below the cursor's still has the
     * cursor's second ahead of it, one ranked above has already given it, and the cursor's own kind goes on
     * by id within that second.
     */
    public function applyTo(QueryBuilder $query, WallItemKind $kind, string $dateField, string $idField): QueryBuilder
    {
        // The columns hold the server's local time, so the bound date is read in that zone too.
        $date = new \DateTimeImmutable('@' . $this->timestamp)->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $query->setParameter('wallCursorDate', $date);

        if ($kind->rank() < $this->kind->rank()) {
            return $query->andWhere(sprintf('%s <= :wallCursorDate', $dateField));
        }
        if ($kind->rank() > $this->kind->rank()) {
            return $query->andWhere(sprintf('%s < :wallCursorDate', $dateField));
        }

        return $query
            ->andWhere(sprintf('%1$s < :wallCursorDate OR (%1$s = :wallCursorDate AND %2$s < :wallCursorId)', $dateField, $idField))
            ->setParameter('wallCursorId', $this->kind === WallItemKind::Publication ? (int) $this->id : $this->id);
    }

    /** The merged wall's order, newest first, as a usort comparator. */
    public static function compare(self $a, self $b): int
    {
        return [$b->timestamp, $b->kind->rank(), self::sortableId($b)] <=> [$a->timestamp, $a->kind->rank(), self::sortableId($a)];
    }

    private static function sortableId(self $cursor): int|string
    {
        return $cursor->kind === WallItemKind::Publication ? (int) $cursor->id : $cursor->id;
    }
}
