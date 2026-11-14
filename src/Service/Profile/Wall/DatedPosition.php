<?php

declare(strict_types=1);

namespace App\Service\Profile\Wall;

use Doctrine\ORM\QueryBuilder;

/**
 * A row's place in a list ordered by date then id, `{unix timestamp}_{uuid}` (#1331). A place, not a row: one
 * deleted since still leaves the next page where it was.
 */
final readonly class DatedPosition
{
    /** Unanchored, for a cursor that adds to it. */
    public const string FORMAT = '\d{1,11}_[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    /** Anchored with \z: `$` would also take a trailing newline. */
    public const string PATTERN = '/^' . self::FORMAT . '\z/';

    private function __construct(
        public int $timestamp,
        public string $id,
    ) {
    }

    public static function of(\DateTimeInterface $date, string $id): self
    {
        return new self($date->getTimestamp(), $id);
    }

    /** Null for anything the pattern refuses: the query parameter's own validation has answered 422 by then. */
    public static function parse(string $value): ?self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            return null;
        }
        [$timestamp, $id] = explode('_', $value, 2);

        return new self((int) $timestamp, $id);
    }

    public function toString(): string
    {
        return sprintf('%d_%s', $this->timestamp, $this->id);
    }

    /** Keeps the rows that come after this place, newest first. */
    public function applyAfterNewestFirst(QueryBuilder $query, string $dateField, string $idField): QueryBuilder
    {
        return $this->applyBound($query, $dateField, $idField, '<', '<');
    }

    /** Keeps this place and the rows before it, newest first. */
    public function applyUpToNewestFirst(QueryBuilder $query, string $dateField, string $idField): QueryBuilder
    {
        return $this->applyBound($query, $dateField, $idField, '>', '>=');
    }

    /** Keeps the rows that come after this place, oldest first. */
    public function applyAfterOldestFirst(QueryBuilder $query, string $dateField, string $idField): QueryBuilder
    {
        return $this->applyBound($query, $dateField, $idField, '>', '>');
    }

    /** Keeps this place and the rows before it, oldest first. */
    public function applyUpToOldestFirst(QueryBuilder $query, string $dateField, string $idField): QueryBuilder
    {
        return $this->applyBound($query, $dateField, $idField, '<', '<=');
    }

    /** The date decides, the id within one second. */
    private function applyBound(QueryBuilder $query, string $dateField, string $idField, string $dateOperator, string $idOperator): QueryBuilder
    {
        return $this->bind($query)->andWhere(sprintf(
            '%1$s %3$s :positionDate OR (%1$s = :positionDate AND %2$s %4$s :positionId)',
            $dateField,
            $idField,
            $dateOperator,
            $idOperator,
        ));
    }

    private function bind(QueryBuilder $query): QueryBuilder
    {
        // The columns hold the server's local time, so the bound date is read in that zone too.
        $date = new \DateTimeImmutable('@' . $this->timestamp)->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        return $query->setParameter('positionDate', $date)->setParameter('positionId', $this->id);
    }
}
