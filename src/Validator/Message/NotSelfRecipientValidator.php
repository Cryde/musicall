<?php declare(strict_types=1);

namespace App\Validator\Message;

use App\ApiResource\Message\MessageUser;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class NotSelfRecipientValidator extends ConstraintValidator
{
    public const ERROR_CODE_SELF_RECIPIENT = 'music_all_b8c3e2f1-5a4d-4e6b-9c7f-1a2b3c4d5e6f';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotSelfRecipient) {
            throw new UnexpectedTypeException($constraint, NotSelfRecipient::class);
        }

        if (!$value instanceof MessageUser) {
            throw new UnexpectedValueException($value, MessageUser::class);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser === null) {
            return;
        }

        if ($value->recipient->getId() === $currentUser->getId()) {
            $this->context->buildViolation($constraint->message)
                ->setCode(self::ERROR_CODE_SELF_RECIPIENT)
                ->addViolation();
        }
    }
}
