<?php

declare(strict_types=1);

namespace App\Model\Search;

/** What a service search asks whatever the kind (#1314): where, how far, online too, and the page. */
class ServiceSearch
{
    public ?float $latitude = null;
    public ?float $longitude = null;
    /** In km around the point; none keeps every distance, only sorted by it. */
    public ?int $radius = null;
    /** With a radius, a service given online counts wherever its places are. */
    public bool $online = false;
    public int $page = 1;
    public int $limit;

    public function hasPoint(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function point(): string
    {
        return sprintf('POINT(%F %F)', $this->longitude, $this->latitude);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /**
     * A short page that is not past the end is the last one: it tells the total without counting.
     *
     * @param callable(): int $count
     */
    public function total(int $pageCount, callable $count): int
    {
        return $pageCount < $this->limit && ($pageCount > 0 || $this->page === 1) ? $this->offset() + $pageCount : $count();
    }
}
