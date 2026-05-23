<?php

declare(strict_types=1);

namespace App\Service\Builder\Publication;

use App\ApiResource\Publication\PublicationListItem;
use App\ApiResource\Publication\PublicationListItem\Author;
use App\ApiResource\Publication\PublicationListItem\SubCategory;
use App\Entity\Image\PublicationCover;
use App\Entity\Metric\VoteCache;
use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Entity\User;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class PublicationListItemBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager   $cacheManager,
    ) {
    }

    /**
     * @param Publication[]   $publications
     * @param array<int, int> $userVotesByCacheId vote_cache_id => -1|1
     *
     * @return PublicationListItem[]
     */
    public function buildList(array $publications, array $userVotesByCacheId = []): array
    {
        return array_map(
            fn (Publication $publication): PublicationListItem => $this->buildItem(
                $publication,
                $publication->voteCache instanceof VoteCache ? ($userVotesByCacheId[$publication->voteCache->id] ?? null) : null,
            ),
            $publications,
        );
    }

    public function buildItem(Publication $publication, ?int $userVote = null): PublicationListItem
    {
        $item = new PublicationListItem();
        $item->id = (int) $publication->id;
        $item->title = $publication->title;
        $item->slug = $publication->slug;
        $item->subCategory = $this->buildSubCategory($publication->subCategory);
        $item->author = $this->buildAuthor($publication->author);
        $publicationDatetime = $publication->publicationDatetime;
        assert($publicationDatetime instanceof \DateTimeInterface);
        $item->publicationDatetime = $publicationDatetime;
        $item->cover = $this->buildCoverUrl($publication->cover);
        $item->typeLabel = $publication->getTypeLabel();
        $item->description = $publication->getDescription();

        $voteCache = $publication->voteCache;
        $item->upvotes = $voteCache->upvoteCount ?? 0;
        $item->downvotes = $voteCache->downvoteCount ?? 0;
        $item->userVote = $userVote;

        return $item;
    }

    private function buildSubCategory(PublicationSubCategory $subCategory): SubCategory
    {
        $dto = new SubCategory();
        $dto->id = (int) $subCategory->id;
        $dto->title = $subCategory->title;
        $dto->slug = $subCategory->slug;
        $dto->typeLabel = $subCategory->getTypeLabel();
        $dto->isCourse = $subCategory->getIsCourse();

        return $dto;
    }

    private function buildAuthor(User $user): Author
    {
        $dto = new Author();
        $dto->username = $user->username;
        $dto->deletionDatetime = $user->deletionDatetime;

        return $dto;
    }

    private function buildCoverUrl(?PublicationCover $cover): ?string
    {
        if ($cover && $path = $this->uploaderHelper->asset($cover, 'imageFile')) {
            return $this->cacheManager->getBrowserPath($path, 'publication_cover_300x300');
        }

        return null;
    }
}
