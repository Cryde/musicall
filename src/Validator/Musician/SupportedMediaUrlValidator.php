<?php

declare(strict_types=1);

namespace App\Validator\Musician;

use App\Service\Musician\MediaUrlParser;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class SupportedMediaUrlValidator extends ConstraintValidator
{
    public const string ERROR_CODE = 'music_all_2a3b4c5d-6e7f-8a9b-0c1d-2e3f4a5b6c7d';

    public function __construct(
        private readonly MediaUrlParser $mediaUrlParser,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SupportedMediaUrl) {
            throw new UnexpectedTypeException($constraint, SupportedMediaUrl::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if ($this->mediaUrlParser->parse($value) === null) {
            $this->context->buildViolation($constraint->message)
                ->setCode(self::ERROR_CODE)
                ->addViolation();
        }
    }
}
