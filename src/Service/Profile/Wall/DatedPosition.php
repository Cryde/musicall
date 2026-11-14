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

    /*
     * Each condition below binds its parameters to the query and answers the condition, so a cursor can make it a
     * part of its own (#1331): the most liked order compares a count first.
     */

    /** The rows that come after this place, newest first. */
    public function afterNewestFirst(QueryBuilder $query, string $dateField, string $idField): string
    {
        return $this->bound($query, $dateField, $idField, '<', '<');
    }

    /** This place and the rows before it, newest first. */
    public function upToNewestFirst(QueryBuilder $query, string $dateField, string $idField): string
    {
        return $this->bound($query, $dateField, $idField, '>', '>=');
    }

    /** The rows that come after this place, oldest first. */
    public function afterOldestFirst(QueryBuilder $query, string $dateField, string $idField): string
    {
        return $this->bound($query, $dateField, $idField, '>', '>');
    }

    /** This place and the rows before it, oldest first. */
    public function upToOldestFirst(QueryBuilder $query, string $dateField, string $idField): string
    {
        return $this->bound($query, $dateField, $idField, '<', '<=');
    }

    /** The date decides, the id within one second. */
    private function bound(QueryBuilder $query, string $dateField, string $idField, string $dateOperator, string $idOperator): string
    {
        $this->bind($query);

        return sprintf(
            '%1$s %3$s :positionDate OR (%1$s = :positionDate AND %2$s %4$s :positionId)',
            $dateField,
            $idField,
            $dateOperator,
            $idOperator,
        );
    }

    private function bind(QueryBuilder $query): QueryBuilder
    {
        // The columns hold the server's local time, so the bound date is read in that zone too.
        $date = new \DateTimeImmutable('@' . $this->timestamp)->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        return $query->setParameter('positionDate', $date)->setParameter('positionId', $this->id);
    }
}
