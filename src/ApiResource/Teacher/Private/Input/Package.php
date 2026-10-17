<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private\Input;

use Symfony\Component\Validator\Constraints as Assert;

class Package
{
    #[Assert\NotBlank(message: 'profile.teacher.package.title_required')]
    public ?string $title = null;

    public ?string $description = null;

    #[Assert\Positive(message: 'profile.teacher.package.sessions_count_positive')]
    public ?int $sessionsCount = null;

    #[Assert\NotNull(message: 'profile.teacher.package.price_required')]
    #[Assert\PositiveOrZero(message: 'profile.teacher.package.price_positive')]
    public ?int $price = null;
}
