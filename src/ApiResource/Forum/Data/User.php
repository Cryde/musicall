<?php

declare(strict_types=1);

namespace App\ApiResource\Forum\Data;

class User
{
    public string $id;
    public string $username;

    /** @var array{small: string}|null */
    public ?array $profilePicture = null;
}
