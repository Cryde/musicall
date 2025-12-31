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
        $dto->id = $publication->getId();
        $dto->title = $publication->getTitle();
        $dto->slug = $publication->getSlug();
        $dto->shortDescription = $publication->getShortDescription();
        $dto->content = $publication->getContent();
        $dto->statusId = $publication->getStatus();
        $dto->statusLabel = Publication::STATUS_LABEL[$publication->getStatus()] ?? 'Inconnu';
        $dto->coverUrl = $this->buildCoverUrl($publication);

        $subCategory = $publication->getSubCategory();
        if ($subCategory) {
            $category = new UserPublicationCategory();
            $category->id = $subCategory->getId();
            $category->title = $subCategory->getTitle();
            $category->slug = $subCategory->getSlug();
            $dto->category = $category;
        }

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
