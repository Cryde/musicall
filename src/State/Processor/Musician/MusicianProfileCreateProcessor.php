<?php

declare(strict_types=1);

namespace App\State\Processor\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Musician\MusicianProfileEdit;
use App\Entity\Musician\MusicianProfile;
use App\Entity\Musician\MusicianProfileInstrument;
use App\Entity\User;
use App\Enum\Musician\AvailabilityStatus;
use App\Enum\Musician\SkillLevel;
use App\Repository\Attribute\InstrumentRepository;
use App\Repository\Attribute\StyleRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<MusicianProfileEdit, MusicianProfileEdit>
 */
readonly class MusicianProfileCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private InstrumentRepository $instrumentRepository,
        private StyleRepository $styleRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MusicianProfileEdit
    {
        /** @var MusicianProfileEdit $data */
        /** @var User|null $user */
        $user = $this->security->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException();
        }

        if ($user->musicianProfile) {
            throw new BadRequestHttpException('Vous avez déjà un profil musicien');
        }

        $profile = new MusicianProfile();
        $profile->user = $user;

        $this->updateProfile($profile, $data);

        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        $data->id = $profile->id;

        return $data;
    }

    private function updateProfile(MusicianProfile $profile, MusicianProfileEdit $data): void
    {
        // Availability status
        if ($data->availabilityStatus) {
            $status = AvailabilityStatus::tryFrom($data->availabilityStatus);
            $profile->availabilityStatus = $status;
        } else {
            $profile->availabilityStatus = null;
        }

        // Instruments
        foreach ($profile->instruments->toArray() as $instrument) {
            $profile->removeInstrument($instrument);
        }

        foreach ($data->instruments as $instrumentInput) {
            $instrument = $this->instrumentRepository->find($instrumentInput->instrumentId);
            if (!$instrument instanceof \App\Entity\Attribute\Instrument) {
                continue;
            }

            $skillLevel = SkillLevel::tryFrom($instrumentInput->skillLevel);
            if (!$skillLevel) {
                continue;
            }

            $profileInstrument = new MusicianProfileInstrument();
            $profileInstrument->instrument = $instrument;
            $profileInstrument->skillLevel = $skillLevel;
            $profile->addInstrument($profileInstrument);
        }

        // Styles
        foreach ($profile->styles->toArray() as $style) {
            $profile->removeStyle($style);
        }

        foreach ($data->styleIds as $styleId) {
            $style = $this->styleRepository->find($styleId);
            if ($style instanceof \App\Entity\Attribute\Style) {
                $profile->addStyle($style);
            }
        }

        $profile->updateDatetime = new DateTimeImmutable();
    }
}
