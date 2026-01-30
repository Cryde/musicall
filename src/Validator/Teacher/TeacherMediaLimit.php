<?php

declare(strict_types=1);

namespace App\Validator\Teacher;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class TeacherMediaLimit extends Constraint
{
    public const int MAX_MEDIA_PER_PROFILE = 6;

    public string $message = 'Vous ne pouvez pas ajouter plus de {{ limit }} médias';

    public function __construct(
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        $this->message = $message ?? $this->message;
        parent::__construct(null, $groups, $payload);
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
