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
use App\Translation\ReferenceNameTranslator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @implements ProviderInterface<MusicianProfileEdit>
 */
readonly class MusicianProfileEditProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private TranslatorInterface $translator,
        private ReferenceNameTranslator $referenceNames,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MusicianProfileEdit
    {
        /** @var User $user */
        $user = $this->security->getUser();
        if (!$musicianProfile = $user->musicianProfile) {
            throw new NotFoundHttpException('profile.musician.not_found');
        }

        return $this->buildFromEntity($musicianProfile);
    }

    private function buildFromEntity(MusicianProfile $profile): MusicianProfileEdit
    {
        $dto = new MusicianProfileEdit();
        $dto->id = $profile->id;

        if ($profile->availabilityStatus instanceof \App\Enum\Musician\AvailabilityStatus) {
            $dto->availabilityStatus = $profile->availabilityStatus->value;
            $dto->availabilityStatusLabel = $profile->availabilityStatus->trans($this->translator);
        }

        $dto->instruments = array_values(array_map(function (\App\Entity\Musician\MusicianProfileInstrument $instrument): MusicianProfileEditInstrument {
            $item = new MusicianProfileEditInstrument();
            $item->instrumentId = (string) $instrument->instrument->id;
            $item->instrumentName = $this->referenceNames->instrumentMusicianName($instrument->instrument);
            $item->skillLevel = $instrument->skillLevel->value;
            $item->skillLevelLabel = $instrument->skillLevel->trans($this->translator);

            return $item;
        }, $profile->instruments->toArray()));

        $dto->styles = array_values(array_map(function (\App\Entity\Attribute\Style $style): MusicianProfileEditStyle {
            $item = new MusicianProfileEditStyle();
            $item->id = (string) $style->id;
            $item->name = $this->referenceNames->style($style);

            return $item;
        }, $profile->styles->toArray()));

        return $dto;
    }
}
