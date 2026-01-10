<?php

declare(strict_types=1);

namespace App\ApiResource\User\Profile;

class PublicProfileAnnounce
{
    public string $id;

    public \DateTimeInterface $creationDatetime;

    public int $type;

    public string $instrumentName;

    public string $locationName;

    /** @var string[] */
    public array $styles = [];
}
