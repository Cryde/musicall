<?php

declare(strict_types=1);

namespace App\Validator\Musician;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class SupportedMediaUrl extends Constraint
{
    public string $message = 'URL non reconnue. Seuls YouTube, SoundCloud et Spotify sont supportés.';

    public function __construct(
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        $this->message = $message ?? $this->message;
        parent::__construct(null, $groups, $payload);
    }
}
