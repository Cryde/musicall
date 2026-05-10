<?php declare(strict_types=1);

namespace App\Service\Builder\User\Publication;

use App\ApiResource\User\Publication\UserPublication;
use App\ApiResource\User\Publication\UserPublicationCategory;
use App\Entity\Publication;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class UserPublicationBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
    ) {
    }

    public function buildFromEntity(Publication $publication): UserPublication
    {
        $creationDatetime = $publication->creationDatetime;

        $dto = new UserPublication();
        $dto->id = (int) $publication->id;
        $dto->title = $publication->title;
        $dto->slug = $publication->slug;
        $dto->creationDatetime = $creationDatetime;
        $dto->editionDatetime = $publication->editionDatetime;
        $dto->statusId = $publication->status;
        $dto->statusLabel = Publication::STATUS_LABEL[$publication->status] ?? 'Inconnu';
        $dto->typeId = (int) $publication->type;
        $dto->typeLabel = $publication->type === Publication::TYPE_VIDEO
            ? Publication::TYPE_VIDEO_LABEL
            : Publication::TYPE_TEXT_LABEL;

        $subCategory = $publication->subCategory;
        $category = new UserPublicationCategory();
        $category->id = (int) $subCategory->id;
        $category->title = $subCategory->title;
        $category->slug = $subCategory->slug;
        $dto->category = $category;

        $dto->coverUrl = $this->buildCoverUrl($publication);

        return $dto;
    }

    private function buildCoverUrl(Publication $publication): ?string
    {
        $cover = $publication->cover;
        if (!$cover instanceof \App\Entity\Image\PublicationCover) {
            return null;
        }

        $path = $this->uploaderHelper->asset($cover, 'imageFile');
        if ($path === null) {
            return null;
        }

        return $this->cacheManager->getBrowserPath($path, 'publication_cover_300x300');
    }

    /**
     * @param Publication[] $publications
     * @return UserPublication[]
     */
    public function buildFromEntities(array $publications): array
    {
        return array_map(fn(Publication $p): \App\ApiResource\User\Publication\UserPublication => $this->buildFromEntity($p), $publications);
    }
}
