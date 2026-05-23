<?php declare(strict_types=1);

namespace App\Service\Builder\Publication;

use App\ApiResource\Publication\Publication;
use App\ApiResource\Publication\Publication\Author;
use App\ApiResource\Publication\Publication\Category;
use App\ApiResource\Publication\Publication\Cover;
use App\ApiResource\Publication\Publication\Tag;
use App\ApiResource\Publication\Publication\Thread;
use App\ApiResource\Publication\Publication\Type;
use App\Entity\Comment\CommentThread;
use App\Entity\Image\PublicationCover;
use App\Entity\Metric\VoteCache;
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
        private HtmlSanitizerInterface $appPublicationSanitizer,
    ) {
    }

    /**
     * @param PublicationEntity[] $entities
     * @param array<int, int>     $userVotesByCacheId vote_cache_id => -1|1
     *
     * @return Publication[]
     */
    public function buildList(array $entities, array $userVotesByCacheId = []): array
    {
        return array_map(
            fn (PublicationEntity $entity): Publication => $this->buildItem(
                $entity,
                $entity->voteCache instanceof VoteCache ? ($userVotesByCacheId[$entity->voteCache->id] ?? null) : null,
            ),
            $entities,
        );
    }

    public function buildItem(PublicationEntity $publicationEntity, ?int $userVote = null): Publication
    {
        $author = $publicationEntity->author;
        $subCategory = $publicationEntity->subCategory;
        $cover = $publicationEntity->cover;
        $thread = $publicationEntity->thread;
        assert($cover instanceof \App\Entity\Image\PublicationCover && $thread instanceof \App\Entity\Comment\CommentThread);

        $publication = new Publication();
        $publication->id = (int) $publicationEntity->id;
        $publication->slug = $publicationEntity->slug;
        $publication->content = $this->appPublicationSanitizer->sanitize((string) $publicationEntity->content);
        $publication->title = $publicationEntity->title;
        $publication->description = $publicationEntity->getDescription() ?? '';
        $publicationDatetime = $publicationEntity->publicationDatetime;
        assert($publicationDatetime instanceof \DateTimeInterface);
        $publication->publicationDatetime = $publicationDatetime;
        $publication->author = $this->buildAuthor($author);
        $publication->cover = $this->buildCover($cover);
        $publication->category = $this->buildCategory($subCategory);
        $publication->thread = $this->buildThread($thread);
        $publication->type = $this->buildType((int) $publicationEntity->type);
        $publication->tags = $this->buildTags($publicationEntity);
        $publication->viewCount = $publicationEntity->viewCache->count ?? 0;
        $publication->readingTime = $publicationEntity->type === PublicationEntity::TYPE_TEXT
            ? $this->calculateReadingTime($publication->content)
            : 0;

        $voteCache = $publicationEntity->voteCache;
        $publication->upvotes = $voteCache->upvoteCount ?? 0;
        $publication->downvotes = $voteCache->downvoteCount ?? 0;
        $publication->userVote = $userVote;

        return $publication;
    }

    private function buildAuthor(User $user): Author
    {
        $author = new Author();
        $author->username = $user->username;
        $author->deletionDatetime = $user->deletionDatetime;

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
        $category->id = (int) $publicationSubCategory->id;
        $category->slug = $publicationSubCategory->slug;
        $category->title = $publicationSubCategory->title;
        $category->isCourse = $publicationSubCategory->getIsCourse();

        return $category;
    }

    private function buildThread(CommentThread $commentThreadEntity): Thread
    {
        $thread = new Thread();
        $thread->id = $commentThreadEntity->id;

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

    private const int READING_WORDS_PER_MINUTE = 200;

    private function calculateReadingTime(string $sanitizedHtml): int
    {
        $plain = trim(html_entity_decode(strip_tags($sanitizedHtml)));
        if ($plain === '') {
            return 1;
        }
        $words = preg_split('/\s+/u', $plain) ?: [];
        $count = count(array_filter($words, static fn (string $w): bool => $w !== ''));

        return max(1, (int) ceil($count / self::READING_WORDS_PER_MINUTE));
    }

    /**
     * @return Tag[]
     */
    private function buildTags(PublicationEntity $publicationEntity): array
    {
        $tags = [];
        foreach ($publicationEntity->tags as $tagEntity) {
            $tag = new Tag();
            $tag->slug = $tagEntity->slug;
            $tag->label = $tagEntity->label;
            $tags[] = $tag;
        }

        return $tags;
    }
}
