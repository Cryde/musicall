<?php

declare(strict_types=1);

namespace App\Validator\User;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class SupportedLocaleValidator extends ConstraintValidator
{
    public const string UNSUPPORTED_LOCALE_ERROR = 'music_all_a16a08ba-d862-4666-a61c-3aa93707c199';

    /**
     * @param string[] $enabledLocales
     */
    public function __construct(
        #[Autowire(param: 'kernel.enabled_locales')]
        private readonly array $enabledLocales,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SupportedLocale) {
            throw new UnexpectedTypeException($constraint, SupportedLocale::class);
        }

        if (!in_array($value, $this->enabledLocales, true)) {
            $this->context->buildViolation($constraint->message)
                ->setCode(self::UNSUPPORTED_LOCALE_ERROR)
                ->addViolation();
        }
    }
}
