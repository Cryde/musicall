<?php

declare(strict_types=1);

namespace App\Validator\User;

use App\ApiResource\User\DeleteAccount;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class DeleteAccountPasswordValidValidator extends ConstraintValidator
{
    public const string ERROR_CODE_INVALID = 'music_all_d3a1f9b2-7c4e-4a8d-b5f6-9e2c1d7a3b08';
    public const string ERROR_CODE_REQUIRED = 'music_all_e4b2a0c3-8d5f-4b9e-c6a7-0f3d2e8b4c19';

    public function __construct(
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DeleteAccountPasswordValid) {
            throw new UnexpectedTypeException($constraint, DeleteAccountPasswordValid::class);
        }

        if (!$value instanceof DeleteAccount) {
            throw new UnexpectedValueException($value, DeleteAccount::class);
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        if ($user === null) {
            return;
        }

        if ($user->getPassword() === null) {
            return;
        }

        if ($value->password === null || $value->password === '') {
            $this->context->buildViolation($constraint->messageRequired)
                ->atPath('password')
                ->setCode(self::ERROR_CODE_REQUIRED)
                ->addViolation();

            return;
        }

        if (!$this->passwordHasher->isPasswordValid($user, $value->password)) {
            $this->context->buildViolation($constraint->messageInvalid)
                ->atPath('password')
                ->setCode(self::ERROR_CODE_INVALID)
                ->addViolation();
        }
    }
}
