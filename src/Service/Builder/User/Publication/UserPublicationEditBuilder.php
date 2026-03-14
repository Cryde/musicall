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
        $dto->id = (int) $publication->id;
        $dto->title = (string) $publication->title;
        $dto->slug = $publication->slug;
        $dto->shortDescription = $publication->shortDescription;
        $dto->content = $publication->content;
        $dto->statusId = (int) $publication->status;
        $dto->statusLabel = Publication::STATUS_LABEL[$publication->status] ?? 'Inconnu';
        $dto->coverUrl = $this->buildCoverUrl($publication);

        $subCategory = $publication->subCategory;
        $category = new UserPublicationCategory();
        $category->id = (int) $subCategory->id;
        $category->title = (string) $subCategory->title;
        $category->slug = (string) $subCategory->slug;
        $dto->category = $category;

        return $dto;
    }

    private function buildCoverUrl(Publication $publication): ?string
    {
        $cover = $publication->cover;
        if (!$cover || !$cover->imageName) {
            return null;
        }

        $path = $this->uploaderHelper->asset($cover, 'imageFile');
        if (!$path) {
            return null;
        }

        return $this->cacheManager->getBrowserPath($path, 'publication_cover_300x300');
    }
}
