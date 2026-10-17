<?php

declare(strict_types=1);

namespace App\Validator\Musician;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class SupportedMediaUrl extends Constraint
{
    public string $message = 'profile.media.url_not_supported';

    public function __construct(
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        $this->message = $message ?? $this->message;
        parent::__construct(null, $groups, $payload);
    }
}
