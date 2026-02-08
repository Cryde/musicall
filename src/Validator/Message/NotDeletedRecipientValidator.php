<?php

declare(strict_types=1);

namespace App\Validator\Message;

use App\ApiResource\Message\MessageUser;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class NotDeletedRecipientValidator extends ConstraintValidator
{
    public const string ERROR_CODE_DELETED_RECIPIENT = 'music_all_f7a2c4d6-3b8e-4f1a-9d5c-6e0b7a8c2d14';

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotDeletedRecipient) {
            throw new UnexpectedTypeException($constraint, NotDeletedRecipient::class);
        }

        if (!$value instanceof MessageUser) {
            throw new UnexpectedValueException($value, MessageUser::class);
        }

        if ($value->recipient->isDeleted()) {
            $this->context->buildViolation($constraint->message)
                ->setCode(self::ERROR_CODE_DELETED_RECIPIENT)
                ->addViolation();
        }
    }
}
