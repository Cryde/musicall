<?php

declare(strict_types=1);

namespace App\Service\Identifier;

use Symfony\Component\HttpFoundation\Request;

/**
 * Tells visitors apart within one day and never across two (#1075): the day is part of the hash, so
 * two searches on different days cannot be linked to the same person, and the IP is never stored.
 */
readonly class DailyVisitorIdentifier
{
    public function __construct(private string $secret)
    {
    }

    public function fromRequest(Request $request, \DateTimeInterface $day): string
    {
        return hash('sha256', $day->format('Y-m-d') . '|' . $request->getClientIp() . '|' . $this->secret);
    }
}
