<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Public;

class TeacherProfilePackage
{
    public string $id;

    public string $title;

    public ?string $description = null;

    public ?int $sessionsCount = null;

    public int $price;
}
