<?php

declare(strict_types=1);

namespace App\ApiResource\Publication\PublicationListItem;

class Author
{
    public string $username;
    public ?\DateTimeImmutable $deletionDatetime = null;
}
