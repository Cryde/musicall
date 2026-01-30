<?php

declare(strict_types=1);

namespace App\Validator\Teacher;

use App\Entity\User;
use App\Repository\Teacher\TeacherProfileMediaRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class TeacherMediaLimitValidator extends ConstraintValidator
{
    public const string ERROR_CODE = 'music_all_8f9e0d1c-2a3b-4c5d-6e7f-8a9b0c1d2e3f';

    public function __construct(
        private readonly Security $security,
        private readonly TeacherProfileMediaRepository $mediaRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TeacherMediaLimit) {
            throw new UnexpectedTypeException($constraint, TeacherMediaLimit::class);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser === null) {
            return;
        }

        $profile = $currentUser->getTeacherProfile();
        if ($profile === null) {
            return;
        }

        $profileId = $profile->getId();
        if ($profileId === null) {
            return;
        }

        $currentCount = $this->mediaRepository->countByTeacherProfile($profileId);
        if ($currentCount >= TeacherMediaLimit::MAX_MEDIA_PER_PROFILE) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ limit }}', (string) TeacherMediaLimit::MAX_MEDIA_PER_PROFILE)
                ->setCode(self::ERROR_CODE)
                ->addViolation();
        }
    }
}
