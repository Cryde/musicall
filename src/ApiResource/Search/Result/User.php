<?php declare(strict_types=1);

namespace App\ApiResource\Search\Result;

class User
{
    public string $id;
    public string $username;
    public string $profilePictureUrl;
    public bool $hasMusicianProfile = false;
}
