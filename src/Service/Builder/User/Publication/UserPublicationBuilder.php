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
        $creationDatetime = $publication->getCreationDatetime();

        $dto = new UserPublication();
        $dto->id = (int) $publication->getId();
        $dto->title = (string) $publication->getTitle();
        $dto->slug = (string) $publication->getSlug();
        $dto->creationDatetime = $creationDatetime;
        $dto->editionDatetime = $publication->getEditionDatetime();
        $dto->statusId = (int) $publication->getStatus();
        $dto->statusLabel = Publication::STATUS_LABEL[$publication->getStatus()] ?? 'Inconnu';
        $dto->typeId = (int) $publication->getType();
        $dto->typeLabel = $publication->getType() === Publication::TYPE_VIDEO
            ? Publication::TYPE_VIDEO_LABEL
            : Publication::TYPE_TEXT_LABEL;

        $subCategory = $publication->getSubCategory();
        $category = new UserPublicationCategory();
        $category->id = (int) $subCategory->getId();
        $category->title = (string) $subCategory->getTitle();
        $category->slug = (string) $subCategory->getSlug();
        $dto->category = $category;

        $dto->coverUrl = $this->buildCoverUrl($publication);

        return $dto;
    }

    private function buildCoverUrl(Publication $publication): ?string
    {
        $cover = $publication->getCover();
        if ($cover === null) {
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
        return array_map(fn(Publication $p) => $this->buildFromEntity($p), $publications);
    }
}
