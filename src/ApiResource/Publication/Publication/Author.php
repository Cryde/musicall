<?php declare(strict_types=1);

namespace App\ApiResource\Publication\Publication;
class Author
{
    public string $username;
    public ?\DateTimeImmutable $deletionDatetime = null;
}
