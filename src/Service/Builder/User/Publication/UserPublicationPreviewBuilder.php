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
        $dto->id = $publication->getId();
        $dto->title = $publication->getTitle();
        $dto->slug = $publication->getSlug();
        $dto->shortDescription = $publication->getShortDescription();
        $dto->content = $this->appPublicationSanitizer->sanitize($publication->getContent() ?? '');
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

        $author = $publication->getAuthor();
        if ($author) {
            $authorDto = new UserPublicationPreviewAuthor();
            $authorDto->username = $author->getUsername();
            $dto->author = $authorDto;
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
