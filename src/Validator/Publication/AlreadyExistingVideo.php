<?php declare(strict_types=1);

namespace App\Validator\Publication;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class AlreadyExistingVideo extends Constraint
{
    public string $message = 'Cette vidéo existe déjà sur MusicAll';

    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        $this->message = $message ?? $this->message;
        parent::__construct(null, $groups, $payload);
    }
}
