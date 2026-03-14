<?php

declare(strict_types=1);

namespace App\Service\Builder\Publication;

use App\ApiResource\Publication\PublicationListItem;
use App\ApiResource\Publication\PublicationListItem\Author;
use App\ApiResource\Publication\PublicationListItem\SubCategory;
use App\Entity\Image\PublicationCover;
use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Entity\User;
use App\Repository\Metric\VoteRepository;
use App\Service\Identifier\RequestIdentifier;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class PublicationListItemBuilder
{
    public function __construct(
        private UploaderHelper    $uploaderHelper,
        private CacheManager      $cacheManager,
        private VoteRepository    $voteRepository,
        private Security          $security,
        private RequestIdentifier $requestIdentifier,
        private RequestStack      $requestStack,
    ) {
    }

    /**
     * @param Publication[] $publications
     *
     * @return PublicationListItem[]
     */
    public function buildFromEntities(array $publications): array
    {
        return array_map(
            fn (Publication $publication): PublicationListItem => $this->buildFromEntity($publication),
            $publications,
        );
    }

    public function buildFromEntity(Publication $publication): PublicationListItem
    {
        $item = new PublicationListItem();
        $item->id = (int) $publication->id;
        $item->title = (string) $publication->title;
        $item->slug = $publication->slug;
        $item->subCategory = $this->buildSubCategory($publication->subCategory);
        $item->author = $this->buildAuthor($publication->author);
        $publicationDatetime = $publication->publicationDatetime;
        assert($publicationDatetime !== null);
        $item->publicationDatetime = $publicationDatetime;
        $item->cover = $this->buildCoverUrl($publication->cover);
        $item->typeLabel = $publication->getTypeLabel();
        $item->description = $publication->getDescription();

        $voteCache = $publication->getVoteCache();
        $item->upvotes = $voteCache->upvoteCount ?? 0;
        $item->downvotes = $voteCache->downvoteCount ?? 0;
        $item->userVote = $this->resolveUserVote($publication);

        return $item;
    }

    private function buildSubCategory(PublicationSubCategory $subCategory): SubCategory
    {
        $dto = new SubCategory();
        $dto->id = (int) $subCategory->id;
        $dto->title = (string) $subCategory->title;
        $dto->slug = (string) $subCategory->slug;
        $dto->typeLabel = $subCategory->getTypeLabel();
        $dto->isCourse = $subCategory->getIsCourse();

        return $dto;
    }

    private function buildAuthor(User $user): Author
    {
        $dto = new Author();
        $dto->username = (string) $user->getUsername();
        $dto->deletionDatetime = $user->getDeletionDatetime();

        return $dto;
    }

    private function buildCoverUrl(?PublicationCover $cover): ?string
    {
        if ($cover && $path = $this->uploaderHelper->asset($cover, 'imageFile')) {
            return $this->cacheManager->getBrowserPath($path, 'publication_cover_300x300');
        }

        return null;
    }

    private function resolveUserVote(Publication $publication): ?int
    {
        $voteCache = $publication->getVoteCache();
        if (!$voteCache) {
            return null;
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        if ($user) {
            $vote = $this->voteRepository->findOneByUserAndVoteCache($user, $voteCache);

            return $vote?->value;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $identifier = $this->requestIdentifier->fromRequest($request);
            $vote = $this->voteRepository->findOneByIdentifierAndVoteCache($identifier, $voteCache);

            return $vote?->value;
        }

        return null;
    }
}
