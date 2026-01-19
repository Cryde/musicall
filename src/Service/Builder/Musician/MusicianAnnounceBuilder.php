<?php declare(strict_types=1);

namespace App\Service\Builder\Musician;

use App\ApiResource\Musician\Announce\Author;
use App\ApiResource\Musician\Announce\Instrument;
use App\ApiResource\Musician\Announce\Style;
use App\ApiResource\Musician\MusicianAnnounce as MusicianAnnounceDTO;
use App\Entity\Attribute\Instrument as InstrumentEntity;
use App\Entity\Attribute\Style as StyleEntity;
use App\Entity\Musician\MusicianAnnounce as MusicianAnnounceEntity;
use App\Entity\User;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class MusicianAnnounceBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
    ) {
    }
    /**
     * @param MusicianAnnounceEntity[] $entities
     * @return MusicianAnnounceDTO[]
     */
    public function buildList(array $entities): array
    {
        return array_map(
            fn(MusicianAnnounceEntity $entity): MusicianAnnounceDTO => $this->buildItem($entity),
            $entities
        );
    }

    public function buildItem(MusicianAnnounceEntity $entity): MusicianAnnounceDTO
    {
        $instrument = $entity->getInstrument();
        $creationDatetime = $entity->getCreationDatetime();

        $dto = new MusicianAnnounceDTO();
        $dto->id = (string) $entity->getId();
        $dto->creationDatetime = $creationDatetime;
        $dto->type = (int) $entity->getType();
        $dto->instrument = $this->buildInstrument($instrument);
        $dto->styles = $this->buildStyles($entity->getStyles()->toArray());
        $dto->locationName = (string) $entity->getLocationName();
        $dto->note = $entity->getNote();
        $dto->author = $this->buildAuthor($entity->getAuthor());

        return $dto;
    }

    private function buildInstrument(InstrumentEntity $entity): Instrument
    {
        $dto = new Instrument();
        $dto->id = (string) $entity->getId();
        $dto->musicianName = (string) $entity->getMusicianName();

        return $dto;
    }

    /**
     * @param StyleEntity[] $entities
     * @return Style[]
     */
    private function buildStyles(array $entities): array
    {
        return array_map(function (StyleEntity $entity): Style {
            $dto = new Style();
            $dto->id = (string) $entity->getId();
            $dto->name = (string) $entity->getName();

            return $dto;
        }, $entities);
    }

    private function buildAuthor(User $user): Author
    {
        $dto = new Author();
        $dto->id = (string) $user->getId();
        $dto->username = $user->getUsername();
        $dto->hasMusicianProfile = $user->getMusicianProfile() !== null;

        if ($user->getProfilePicture()) {
            $path = $this->uploaderHelper->asset($user->getProfilePicture(), 'imageFile');
            if ($path !== null) {
                $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
            }
        }

        return $dto;
    }
}
