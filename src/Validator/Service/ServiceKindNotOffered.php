<?php

declare(strict_types=1);

namespace App\Validator\Service;

use Symfony\Component\Validator\Constraint;

/** One service per kind per member (#1406). */
#[\Attribute]
class ServiceKindNotOffered extends Constraint
{
    public string $message = 'service.kind.already_offered';
}
