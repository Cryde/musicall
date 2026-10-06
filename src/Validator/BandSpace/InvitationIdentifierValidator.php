<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\Entity\User;
use App\Repository\User\Relation\UserBlockRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class InvitationIdentifierValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserBlockRepository $userBlockRepository,
        private readonly Security $security,
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
            if (!$user instanceof User || $this->isBlockedWithInviter($user)) {
                $this->context->buildViolation($constraint->usernameNotFoundMessage)
                    ->setCode(InvitationIdentifier::USERNAME_NOT_FOUND_ERROR)
                    ->addViolation();
            }
        }
    }

    /**
     * Someone blocked with the inviter, either way, reads as an unknown username (#1117): any other
     * answer would tell the inviter they are blocked. Inviting by email stays open, as the address
     * was typed by hand.
     */
    private function isBlockedWithInviter(User $invitee): bool
    {
        $inviter = $this->security->getUser();

        return $inviter instanceof User && $this->userBlockRepository->isBlockedEitherWay($inviter, $invitee);
    }
}
