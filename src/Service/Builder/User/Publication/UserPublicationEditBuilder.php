<?php declare(strict_types=1);

namespace App\Service\Builder\User\Publication;

use App\ApiResource\User\Publication\UserPublicationCategory;
use App\ApiResource\User\Publication\UserPublicationEdit;
use App\Entity\Publication;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class UserPublicationEditBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
    ) {
    }

    public function buildFromEntity(Publication $publication): UserPublicationEdit
    {
        $dto = new UserPublicationEdit();
        $dto->id = (int) $publication->getId();
        $dto->title = (string) $publication->getTitle();
        $dto->slug = (string) $publication->getSlug();
        $dto->shortDescription = $publication->getShortDescription();
        $dto->content = $publication->getContent();
        $dto->statusId = (int) $publication->getStatus();
        $dto->statusLabel = Publication::STATUS_LABEL[$publication->getStatus()] ?? 'Inconnu';
        $dto->coverUrl = $this->buildCoverUrl($publication);

        $subCategory = $publication->getSubCategory();
        $category = new UserPublicationCategory();
        $category->id = (int) $subCategory->getId();
        $category->title = (string) $subCategory->getTitle();
        $category->slug = (string) $subCategory->getSlug();
        $dto->category = $category;

        return $dto;
    }

    private function buildCoverUrl(Publication $publication): ?string
    {
        $cover = $publication->getCover();
        if (!$cover || !$cover->getImageName()) {
            return null;
        }

        $path = $this->uploaderHelper->asset($cover, 'imageFile');
        if (!$path) {
            return null;
        }

        return $this->cacheManager->getBrowserPath($path, 'publication_cover_300x300');
    }
}
