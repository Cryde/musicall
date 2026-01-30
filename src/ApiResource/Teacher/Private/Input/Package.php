<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private\Input;

use Symfony\Component\Validator\Constraints as Assert;

class Package
{
    #[Assert\NotBlank(message: 'Le titre du forfait est obligatoire')]
    public ?string $title = null;

    public ?string $description = null;

    #[Assert\Positive(message: 'Le nombre de séances doit être positif')]
    public ?int $sessionsCount = null;

    #[Assert\NotNull(message: 'Le prix du forfait est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le prix doit être positif')]
    public ?int $price = null;
}
