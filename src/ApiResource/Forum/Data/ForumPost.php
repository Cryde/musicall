<?php

declare(strict_types=1);

namespace App\ApiResource\Forum\Data;

use ApiPlatform\Metadata\ApiProperty;
use DateTimeInterface;

class ForumPost
{
    public string $id;
    public DateTimeInterface $creationDatetime;

    #[ApiProperty(genId: false)]
    public User $creator;
}
