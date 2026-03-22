<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class InvitationIdentifierValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof InvitationIdentifier) {
            throw new UnexpectedTypeException($constraint, InvitationIdentifier::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $identifier = trim((string) $value);
        $isEmail = str_contains($identifier, '@');

        if ($isEmail) {
            if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $this->context->buildViolation($constraint->invalidEmailMessage)
                    ->setCode(InvitationIdentifier::INVALID_EMAIL_ERROR)
                    ->addViolation();
            }
        } else {
            $user = $this->userRepository->findOneBy(['username' => $identifier]);
            if (!$user) {
                $this->context->buildViolation($constraint->usernameNotFoundMessage)
                    ->setCode(InvitationIdentifier::USERNAME_NOT_FOUND_ERROR)
                    ->addViolation();
            }
        }
    }
}
