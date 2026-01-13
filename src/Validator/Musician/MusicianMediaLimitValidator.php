<?php

declare(strict_types=1);

namespace App\Validator\Musician;

use App\Entity\User;
use App\Repository\Musician\MusicianProfileMediaRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class MusicianMediaLimitValidator extends ConstraintValidator
{
    public const string ERROR_CODE = 'music_all_7f8e9d0c-1a2b-3c4d-5e6f-7a8b9c0d1e2f';

    public function __construct(
        private readonly Security $security,
        private readonly MusicianProfileMediaRepository $mediaRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MusicianMediaLimit) {
            throw new UnexpectedTypeException($constraint, MusicianMediaLimit::class);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser === null) {
            return;
        }

        $profile = $currentUser->getMusicianProfile();
        if ($profile === null) {
            return;
        }

        $currentCount = $this->mediaRepository->countByMusicianProfile($profile->getId());
        if ($currentCount >= MusicianMediaLimit::MAX_MEDIA_PER_PROFILE) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ limit }}', (string) MusicianMediaLimit::MAX_MEDIA_PER_PROFILE)
                ->setCode(self::ERROR_CODE)
                ->addViolation();
        }
    }
}
