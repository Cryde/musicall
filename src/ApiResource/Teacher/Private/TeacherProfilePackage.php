<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private;

class TeacherProfilePackage
{
    public ?string $id = null;

    public string $title;

    public ?string $description = null;

    public ?int $sessionsCount = null;

    public int $price;
}
