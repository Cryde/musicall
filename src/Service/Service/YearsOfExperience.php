<?php

declare(strict_types=1);

namespace App\Service\Service;

use Psr\Clock\ClockInterface;

/** A service stores the year its member started (#1453): the years it shows are counted from it on every read. */
readonly class YearsOfExperience
{
    /** How many years back a start year may go. */
    public const int MAX = 70;

    public function __construct(private ClockInterface $clock)
    {
    }

    public function currentYear(): int
    {
        return (int) $this->clock->now()->format('Y');
    }

    public function since(?int $startYear): ?int
    {
        return $startYear !== null ? $this->currentYear() - $startYear : null;
    }

    /** The start year a count of years stands for: it converts back exactly, so the app's saves never move it. */
    public function startYear(int $years): int
    {
        return $this->currentYear() - $years;
    }
}
