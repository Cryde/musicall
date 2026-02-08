<?php

declare(strict_types=1);

namespace App\Validator\Message;

use App\Entity\Message\Message;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class NotDeletedThreadRecipientValidator extends ConstraintValidator
{
    public const string ERROR_CODE_DELETED_THREAD_RECIPIENT = 'music_all_a9d3b7e1-4c6f-4e2a-8b5d-3f1c0e9a7d26';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotDeletedThreadRecipient) {
            throw new UnexpectedTypeException($constraint, NotDeletedThreadRecipient::class);
        }

        if (!$value instanceof Message) {
            throw new UnexpectedValueException($value, Message::class);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser === null) {
            return;
        }

        foreach ($value->getThread()->getMessageParticipants() as $messageParticipant) {
            $participant = $messageParticipant->getParticipant();
            if ($participant->getId() !== $currentUser->getId() && $participant->isDeleted()) {
                $this->context->buildViolation($constraint->message)
                    ->setCode(self::ERROR_CODE_DELETED_THREAD_RECIPIENT)
                    ->addViolation();

                return;
            }
        }
    }
}
