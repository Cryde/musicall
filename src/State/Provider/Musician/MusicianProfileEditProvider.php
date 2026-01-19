<?php

declare(strict_types=1);

namespace App\State\Provider\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Musician\MusicianProfileEdit;
use App\ApiResource\Musician\MusicianProfileEditInstrument;
use App\ApiResource\Musician\MusicianProfileEditStyle;
use App\Entity\Musician\MusicianProfile;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<MusicianProfileEdit>
 */
readonly class MusicianProfileEditProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MusicianProfileEdit
    {
        if (!$user = $this->security->getUser()) {
            throw new AccessDeniedHttpException();
        }
        /** @var User $user */
        if (!$musicianProfile = $user->getMusicianProfile()) {
            throw new NotFoundHttpException('Profil musicien non trouvé');
        }

        return $this->buildFromEntity($musicianProfile);
    }

    private function buildFromEntity(MusicianProfile $profile): MusicianProfileEdit
    {
        $dto = new MusicianProfileEdit();
        $dto->id = $profile->getId();

        if ($profile->getAvailabilityStatus()) {
            $dto->availabilityStatus = $profile->getAvailabilityStatus()->value;
            $dto->availabilityStatusLabel = $profile->getAvailabilityStatus()->getLabel();
        }

        $dto->instruments = array_values(array_map(function ($instrument): MusicianProfileEditInstrument {
            $item = new MusicianProfileEditInstrument();
            $item->instrumentId = (string) $instrument->getInstrument()->getId();
            $item->instrumentName = (string) $instrument->getInstrument()->getMusicianName();
            $item->skillLevel = $instrument->getSkillLevel()->value;
            $item->skillLevelLabel = $instrument->getSkillLevel()->getLabel();

            return $item;
        }, $profile->getInstruments()->toArray()));

        $dto->styles = array_values(array_map(function ($style): MusicianProfileEditStyle {
            $item = new MusicianProfileEditStyle();
            $item->id = (string) $style->getId();
            $item->name = (string) $style->getName();

            return $item;
        }, $profile->getStyles()->toArray()));

        return $dto;
    }
}
