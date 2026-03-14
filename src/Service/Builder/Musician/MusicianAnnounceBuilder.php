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
        $dto = new MusicianAnnounceDTO();
        $dto->id = (string) $entity->id;
        $dto->creationDatetime = $entity->creationDatetime;
        $dto->type = (int) $entity->type;
        $dto->instrument = $this->buildInstrument($entity->instrument);
        $dto->styles = $this->buildStyles($entity->styles->toArray());
        $dto->locationName = (string) $entity->locationName;
        $dto->note = $entity->note;
        $dto->author = $this->buildAuthor($entity->author);

        return $dto;
    }

    private function buildInstrument(InstrumentEntity $entity): Instrument
    {
        $dto = new Instrument();
        $dto->id = (string) $entity->id;
        $dto->musicianName = (string) $entity->musicianName;

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
            $dto->id = (string) $entity->id;
            $dto->name = (string) $entity->name;

            return $dto;
        }, $entities);
    }

    private function buildAuthor(User $user): Author
    {
        $dto = new Author();
        $dto->id = (string) $user->id;
        $dto->username = $user->username;
        $dto->deletionDatetime = $user->deletionDatetime;
        $dto->hasMusicianProfile = $user->musicianProfile !== null;

        if ($user->profilePicture) {
            $path = $this->uploaderHelper->asset($user->profilePicture, 'imageFile');
            if ($path !== null) {
                $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
            }
        }

        return $dto;
    }
}
