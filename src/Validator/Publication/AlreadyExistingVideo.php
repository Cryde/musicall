<?php

namespace App\Validator\Publication;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class AlreadyExistingVideo extends Constraint
{
    public string $message = 'Cette vidéo existe déjà sur MusicAll';

    public function __construct(string $message = null, array $groups = null, mixed $payload = null)
    {
        parent::__construct([], $groups, $payload);
        $this->message = $message ?? $this->message;
    }
}