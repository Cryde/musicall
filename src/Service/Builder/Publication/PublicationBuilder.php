<?php

namespace App\Service\Builder\Publication;

use App\ApiResource\Publication\Publication;
use App\Entity\Comment\CommentThread;
use App\Entity\Image\PublicationCover;
use App\Entity\Publication as PublicationEntity;
use App\Entity\PublicationSubCategory;
use App\Entity\User;
use App\Enum\Publication\PublicationType;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class PublicationBuilder
{
    public function __construct(
        private UploaderHelper         $uploaderHelper,
        private CacheManager           $cacheManager,
        private HtmlSanitizerInterface $appPublicationSanitizer
    ) {
    }

    public function buildFromEntity(PublicationEntity $publicationEntity): Publication
    {
        $publication = new Publication();
        $publication->slug = $publicationEntity->getSlug();
        $publication->content = $this->appPublicationSanitizer->sanitize($publicationEntity->getContent());
        $publication->title = $publicationEntity->getTitle();
        $publication->description = $publicationEntity->getDescription() ?? '';
        $publication->publicationDatetime = $publicationEntity->getPublicationDatetime();
        $publication->author = $this->buildAuthor($publicationEntity->getAuthor());
        $publication->cover = $this->buildCover($publicationEntity->getCover());
        $publication->category = $this->buildCategory($publicationEntity->getSubCategory());
        $publication->thread = $this->buildThread($publicationEntity->getThread());
        $publication->type = $this->buildType($publicationEntity->getType());

        return $publication;
    }

    private function buildAuthor(User $user): Publication\Author
    {
        $author = new Publication\Author();
        $author->username = $user->getUsername();

        return $author;
    }

    private function buildCover(PublicationCover $publicationCover): Publication\Cover
    {
        $path = $this->uploaderHelper->asset($publicationCover, 'imageFile');
        $cover = new Publication\Cover();
        $cover->coverUrl = $this->cacheManager->getBrowserPath($path, 'publication_cover_300x300');

        return $cover;
    }

    private function buildCategory(PublicationSubCategory $publicationSubCategory): Publication\Category
    {
        $category = new Publication\Category();
        $category->id = $publicationSubCategory->getId();
        $category->slug = $publicationSubCategory->getSlug();
        $category->title = $publicationSubCategory->getTitle();

        return $category;
    }

    private function buildThread(CommentThread $commentThreadEntity): Publication\Thread
    {
        $thread = new Publication\Thread();
        $thread->id = $commentThreadEntity->getId();

        return $thread;
    }

    public function buildType(int $typeValue): Publication\Type
    {
        $publicationType = PublicationType::tryFrom($typeValue) ?: PublicationType::Text;
        $type = new Publication\Type();
        $type->id = $publicationType->value;
        $type->label = $publicationType->label();

        return $type;
    }
}