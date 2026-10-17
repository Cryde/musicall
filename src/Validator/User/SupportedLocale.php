<?php

declare(strict_types=1);

namespace App\Validator\User;

use Symfony\Component\Validator\Constraint;

/** One of `framework.enabled_locales`. */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class SupportedLocale extends Constraint
{
    public string $message = 'user.locale.not_available';
}
