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
use App\Repository\Metric\VoteRepository;
use App\Service\Identifier\RequestIdentifier;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class PublicationBuilder
{
    public function __construct(
        private UploaderHelper         $uploaderHelper,
        private CacheManager           $cacheManager,
        private HtmlSanitizerInterface $appPublicationSanitizer,
        private VoteRepository         $voteRepository,
        private Security               $security,
        private RequestIdentifier      $requestIdentifier,
        private RequestStack           $requestStack,
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
        $author = $publicationEntity->author;
        $subCategory = $publicationEntity->subCategory;
        $cover = $publicationEntity->cover;
        $thread = $publicationEntity->thread;
        assert($cover !== null && $thread !== null);

        $publication = new Publication();
        $publication->slug = $publicationEntity->slug;
        $publication->content = $this->appPublicationSanitizer->sanitize((string) $publicationEntity->content);
        $publication->title = (string) $publicationEntity->title;
        $publication->description = $publicationEntity->getDescription() ?? '';
        $publicationDatetime = $publicationEntity->publicationDatetime;
        assert($publicationDatetime !== null);
        $publication->publicationDatetime = $publicationDatetime;
        $publication->author = $this->buildAuthor($author);
        $publication->cover = $this->buildCover($cover);
        $publication->category = $this->buildCategory($subCategory);
        $publication->thread = $this->buildThread($thread);
        $publication->type = $this->buildType((int) $publicationEntity->type);

        $voteCache = $publicationEntity->getVoteCache();
        $publication->upvotes = $voteCache->upvoteCount ?? 0;
        $publication->downvotes = $voteCache->downvoteCount ?? 0;

        if ($voteCache) {
            /** @var User|null $currentUser */
            $currentUser = $this->security->getUser();
            if ($currentUser) {
                $vote = $this->voteRepository->findOneByUserAndVoteCache($currentUser, $voteCache);
                $publication->userVote = $vote?->value;
            } else {
                $request = $this->requestStack->getCurrentRequest();
                if ($request) {
                    $identifier = $this->requestIdentifier->fromRequest($request);
                    $vote = $this->voteRepository->findOneByIdentifierAndVoteCache($identifier, $voteCache);
                    $publication->userVote = $vote?->value;
                }
            }
        }

        return $publication;
    }

    private function buildAuthor(User $user): Author
    {
        $author = new Author();
        $author->username = (string) $user->username;
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
        $category->slug = (string) $publicationSubCategory->slug;
        $category->title = (string) $publicationSubCategory->title;

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
}
