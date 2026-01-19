<?php declare(strict_types=1);

namespace App\Service\Builder\Publication;

use App\ApiResource\Publication\Publication\Author;
use App\ApiResource\Publication\Publication\Cover;
use App\ApiResource\Publication\Publication\Category;
use App\ApiResource\Publication\Publication\Thread;
use App\ApiResource\Publication\Publication\Type;
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

    /**
     * @param PublicationEntity[] $publicationEntities
     *
     * @return Publication[]
     */
    public function buildFromEntities(array $publicationEntities): array
    {
        return array_map(fn (PublicationEntity $publicationEntity): Publication => $this->buildFromEntity($publicationEntity), $publicationEntities);
    }

    public function buildFromEntity(PublicationEntity $publicationEntity): Publication
    {
        $author = $publicationEntity->getAuthor();
        $subCategory = $publicationEntity->getSubCategory();
        $cover = $publicationEntity->getCover();
        $thread = $publicationEntity->getThread();
        assert($cover !== null && $thread !== null);

        $publication = new Publication();
        $publication->slug = (string) $publicationEntity->getSlug();
        $publication->content = $this->appPublicationSanitizer->sanitize((string) $publicationEntity->getContent());
        $publication->title = (string) $publicationEntity->getTitle();
        $publication->description = $publicationEntity->getDescription() ?? '';
        $publicationDatetime = $publicationEntity->getPublicationDatetime();
        assert($publicationDatetime !== null);
        $publication->publicationDatetime = $publicationDatetime;
        $publication->author = $this->buildAuthor($author);
        $publication->cover = $this->buildCover($cover);
        $publication->category = $this->buildCategory($subCategory);
        $publication->thread = $this->buildThread($thread);
        $publication->type = $this->buildType((int) $publicationEntity->getType());

        return $publication;
    }

    private function buildAuthor(User $user): Author
    {
        $author = new Author();
        $author->username = (string) $user->getUsername();

        return $author;
    }

    private function buildCover(PublicationCover $publicationCover): Cover
    {
        $path = $this->uploaderHelper->asset($publicationCover, 'imageFile');
        assert($path !== null);
        $cover = new Cover();
        $cover->coverUrl = $this->cacheManager->getBrowserPath($path, 'publication_cover_300x300');

        return $cover;
    }

    private function buildCategory(PublicationSubCategory $publicationSubCategory): Category
    {
        $category = new Category();
        $category->id = (int) $publicationSubCategory->getId();
        $category->slug = (string) $publicationSubCategory->getSlug();
        $category->title = (string) $publicationSubCategory->getTitle();

        return $category;
    }

    private function buildThread(CommentThread $commentThreadEntity): Thread
    {
        $thread = new Thread();
        $thread->id = (int) $commentThreadEntity->getId();

        return $thread;
    }

    public function buildType(int $typeValue): Type
    {
        $publicationType = PublicationType::tryFrom($typeValue) ?: PublicationType::Text;
        $type = new Type();
        $type->id = $publicationType->value;
        $type->label = $publicationType->label();

        return $type;
    }
}
