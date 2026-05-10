<?php declare(strict_types=1);

namespace App\Service\Builder\User\Publication;

use App\ApiResource\User\Publication\UserPublicationCategory;
use App\ApiResource\User\Publication\UserPublicationPreview;
use App\ApiResource\User\Publication\UserPublicationPreviewAuthor;
use App\Entity\Publication;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class UserPublicationPreviewBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
        private HtmlSanitizerInterface $appPublicationSanitizer,
    ) {
    }

    public function buildFromEntity(Publication $publication): UserPublicationPreview
    {
        $dto = new UserPublicationPreview();
        $dto->id = (int) $publication->id;
        $dto->title = $publication->title;
        $dto->slug = $publication->slug;
        $dto->shortDescription = $publication->shortDescription;
        $dto->content = $this->appPublicationSanitizer->sanitize($publication->content ?? '');
        $dto->statusId = $publication->status;
        $dto->statusLabel = Publication::STATUS_LABEL[$publication->status] ?? 'Inconnu';
        $dto->coverUrl = $this->buildCoverUrl($publication);

        $subCategory = $publication->subCategory;
        $category = new UserPublicationCategory();
        $category->id = (int) $subCategory->id;
        $category->title = $subCategory->title;
        $category->slug = $subCategory->slug;
        $dto->category = $category;

        $author = $publication->author;
        $authorDto = new UserPublicationPreviewAuthor();
        $authorDto->username = $author->username;
        $dto->author = $authorDto;

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
