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
        /** @var User $user */
        $user = $this->security->getUser();
        if (!$musicianProfile = $user->musicianProfile) {
            throw new NotFoundHttpException('Profil musicien non trouvé');
        }

        return $this->buildFromEntity($musicianProfile);
    }

    private function buildFromEntity(MusicianProfile $profile): MusicianProfileEdit
    {
        $dto = new MusicianProfileEdit();
        $dto->id = $profile->id;

        if ($profile->availabilityStatus) {
            $dto->availabilityStatus = $profile->availabilityStatus->value;
            $dto->availabilityStatusLabel = $profile->availabilityStatus->getLabel();
        }

        $dto->instruments = array_values(array_map(function ($instrument): MusicianProfileEditInstrument {
            $item = new MusicianProfileEditInstrument();
            $item->instrumentId = (string) $instrument->instrument->id;
            $item->instrumentName = (string) $instrument->instrument->musicianName;
            $item->skillLevel = $instrument->skillLevel->value;
            $item->skillLevelLabel = $instrument->skillLevel->getLabel();

            return $item;
        }, $profile->instruments->toArray()));

        $dto->styles = array_values(array_map(function ($style): MusicianProfileEditStyle {
            $item = new MusicianProfileEditStyle();
            $item->id = (string) $style->id;
            $item->name = (string) $style->name;

            return $item;
        }, $profile->styles->toArray()));

        return $dto;
    }
}
