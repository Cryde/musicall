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
use App\State\Provider\Musician\MusicianProfileEditProvider;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<MusicianProfileEdit, MusicianProfileEdit>
 */
readonly class MusicianProfileEditProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private InstrumentRepository $instrumentRepository,
        private StyleRepository $styleRepository,
        private MusicianProfileEditProvider $provider,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MusicianProfileEdit
    {
        /** @var MusicianProfileEdit $data */
        /** @var User $user */
        $user = $this->security->getUser();
        $profile = $user->getMusicianProfile();

        if (!$profile) {
            throw new NotFoundHttpException('Profil musicien non trouvé');
        }

        $this->updateProfile($profile, $data);

        return $this->provider->provide($operation, $uriVariables, $context);
    }

    private function updateProfile(MusicianProfile $profile, MusicianProfileEdit $data): void
    {
        // Availability status
        if ($data->availabilityStatus !== null) {
            $status = AvailabilityStatus::tryFrom($data->availabilityStatus);
            $profile->setAvailabilityStatus($status);
        }

        // Instruments (if provided)
        if (!empty($data->instruments) || $data->instruments === []) {
            // Remove existing instruments and flush first to avoid unique constraint violation
            // (Doctrine processes INSERTs before DELETEs in the same flush)
            foreach ($profile->getInstruments()->toArray() as $instrument) {
                $profile->removeInstrument($instrument);
            }
            $this->entityManager->flush();

            foreach ($data->instruments as $instrumentInput) {
                $instrument = $this->instrumentRepository->find($instrumentInput->instrumentId);
                if (!$instrument) {
                    continue;
                }

                $skillLevel = SkillLevel::tryFrom($instrumentInput->skillLevel);
                if (!$skillLevel) {
                    continue;
                }

                $profileInstrument = new MusicianProfileInstrument();
                $profileInstrument->setInstrument($instrument);
                $profileInstrument->setSkillLevel($skillLevel);
                $profile->addInstrument($profileInstrument);
            }
        }

        // Styles (if provided)
        if (!empty($data->styleIds) || $data->styleIds === []) {
            foreach ($profile->getStyles()->toArray() as $style) {
                $profile->removeStyle($style);
            }

            foreach ($data->styleIds as $styleId) {
                $style = $this->styleRepository->find($styleId);
                if ($style) {
                    $profile->addStyle($style);
                }
            }
        }

        $profile->setUpdateDatetime(new DateTimeImmutable());
        $this->entityManager->flush();
    }
}
