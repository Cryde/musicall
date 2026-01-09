<?php

declare(strict_types=1);

namespace App\Validator\User;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UsernameChangeThrottleValidator extends ConstraintValidator
{
    public const string ERROR_CODE_THROTTLED = 'music_all_a1b2c3d4-5e6f-7a8b-9c0d-1e2f3a4b5c6d';
    private const int COOLDOWN_DAYS = 30;

    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UsernameChangeThrottle) {
            throw new UnexpectedTypeException($constraint, UsernameChangeThrottle::class);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser === null) {
            return;
        }

        $lastChangeDate = $currentUser->getUsernameChangedDatetime();
        if ($lastChangeDate === null) {
            return;
        }

        $cooldownEnd = $lastChangeDate->modify('+' . self::COOLDOWN_DAYS . ' days');
        $now = new \DateTimeImmutable();

        if ($now < $cooldownEnd) {
            $this->context->buildViolation($constraint->message)
                ->setCode(self::ERROR_CODE_THROTTLED)
                ->addViolation();
        }
    }
}
