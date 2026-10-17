<?php declare(strict_types=1);

namespace App\Service\Builder\User\Publication;

use App\ApiResource\User\Publication\UserPublicationCategory;
use App\ApiResource\User\Publication\UserPublicationEdit;
use App\Entity\Publication;
use App\Translation\ReferenceNameTranslator;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class UserPublicationEditBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
        private ReferenceNameTranslator $referenceNames,
    ) {
    }

    public function buildFromEntity(Publication $publication): UserPublicationEdit
    {
        $dto = new UserPublicationEdit();
        $dto->id = (int) $publication->id;
        $dto->title = $publication->title;
        $dto->slug = $publication->slug;
        $dto->shortDescription = $publication->shortDescription;
        $dto->content = $publication->content;
        $dto->statusId = $publication->status;
        $dto->coverUrl = $this->buildCoverUrl($publication);
        $dto->language = $publication->language;

        $subCategory = $publication->subCategory;
        $category = new UserPublicationCategory();
        $category->id = (int) $subCategory->id;
        $category->title = $this->referenceNames->subCategory($subCategory);
        $category->slug = $subCategory->slug;
        $dto->category = $category;

        $dto->tags = array_map(static fn ($tag): string => $tag->label, $publication->tags->toArray());

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
