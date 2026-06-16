<?php declare(strict_types=1);

namespace App\Service\Builder\Musician;

use App\ApiResource\Musician\Announce\Author;
use App\ApiResource\Musician\Announce\Instrument;
use App\ApiResource\Musician\Announce\Style;
use App\ApiResource\Musician\MusicianAnnounce as MusicianAnnounceDTO;
use App\Entity\Attribute\Instrument as InstrumentEntity;
use App\Entity\Attribute\Style as StyleEntity;
use App\Entity\Image\UserProfilePicture;
use App\Entity\Musician\MusicianAnnounce as MusicianAnnounceEntity;
use App\Entity\Musician\MusicianProfile;
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
        return $this->buildDto($entity, $this->buildAuthor($entity->author));
    }

    /**
     * Builds the list for the "last" endpoint, where authors are projected upfront
     * (keyed by announce id) to avoid hydrating a full User per row.
     *
     * @param MusicianAnnounceEntity[] $entities
     * @param array<string, array{id: string, username: string, deletionDatetime: ?\DateTimeImmutable, hasMusicianProfile: bool, profilePictureName: ?string}> $authorsByAnnounceId
     *
     * @return MusicianAnnounceDTO[]
     */
    public function buildListWithProjectedAuthors(array $entities, array $authorsByAnnounceId): array
    {
        return array_map(function (MusicianAnnounceEntity $entity) use ($authorsByAnnounceId): MusicianAnnounceDTO {
            $author = $authorsByAnnounceId[(string) $entity->id];

            return $this->buildDto($entity, $this->createAuthor(
                $author['id'],
                $author['username'],
                $author['deletionDatetime'],
                $author['hasMusicianProfile'],
                $author['profilePictureName'],
            ));
        }, $entities);
    }

    private function buildDto(MusicianAnnounceEntity $entity, Author $author): MusicianAnnounceDTO
    {
        $dto = new MusicianAnnounceDTO();
        $dto->id = (string) $entity->id;
        $dto->creationDatetime = $entity->creationDatetime;
        $dto->type = $entity->type;
        $dto->instrument = $this->buildInstrument($entity->instrument);
        $dto->styles = $this->buildStyles($entity->styles->toArray());
        $dto->locationName = $entity->locationName;
        $dto->note = $entity->note;
        $dto->author = $author;

        return $dto;
    }

    private function buildInstrument(InstrumentEntity $entity): Instrument
    {
        $dto = new Instrument();
        $dto->id = (string) $entity->id;
        $dto->musicianName = $entity->musicianName;

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
            $dto->name = $entity->name;

            return $dto;
        }, $entities);
    }

    private function buildAuthor(User $user): Author
    {
        return $this->createAuthor(
            $user->id,
            $user->username,
            $user->deletionDatetime,
            $user->musicianProfile instanceof MusicianProfile,
            $user->profilePicture?->imageName,
        );
    }

    private function createAuthor(
        string $id,
        string $username,
        ?\DateTimeImmutable $deletionDatetime,
        bool $hasMusicianProfile,
        ?string $profilePictureName,
    ): Author {
        $dto = new Author();
        $dto->id = $id;
        $dto->username = $username;
        $dto->deletionDatetime = $deletionDatetime;
        $dto->hasMusicianProfile = $hasMusicianProfile;

        if ($profilePictureName !== null) {
            // The picture's asset path depends only on its imageName (the mapping uses a
            // static directory namer), so a transient instance is enough to resolve it
            // without hydrating the owning UserProfilePicture entity per row.
            $picture = new UserProfilePicture();
            $picture->imageName = $profilePictureName;
            $path = $this->uploaderHelper->asset($picture, 'imageFile');
            if ($path !== null) {
                $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
            }
        }

        return $dto;
    }
}
