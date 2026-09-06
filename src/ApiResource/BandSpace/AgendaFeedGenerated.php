<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

/**
 * The one and only time the plaintext feed token is readable.
 *
 * Only its sha256 is stored, so this URL cannot be shown again: losing it means generating a new
 * one, which retires the old. The section that renders this says so.
 */
class AgendaFeedGenerated
{
    public string $feedUrl;

    public \DateTimeInterface $creationDatetime;
}
