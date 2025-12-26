<?php declare(strict_types=1);

namespace App\Validator\Publication;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UrlVideo extends Constraint
{
    public string $message = 'L\'url de cette vidéo n\'est pas supportée';

    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        $this->message = $message ?? $this->message;
        parent::__construct(null, $groups, $payload);
    }
}
