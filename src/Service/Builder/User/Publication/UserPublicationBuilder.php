<?php declare(strict_types=1);

namespace App\Service\Builder\User\Publication;

use App\ApiResource\User\Publication\UserPublication;
use App\ApiResource\User\Publication\UserPublicationCategory;
use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Translation\ReferenceNameTranslator;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class UserPublicationBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
        private ReferenceNameTranslator $referenceNames,
        private PublicationRepository $publicationRepository,
    ) {
    }

    /**
     * @param string[] $groupLanguages the languages of every publication in its translation group, itself included
     */
    public function buildFromEntity(Publication $publication, array $groupLanguages = []): UserPublication
    {
        $creationDatetime = $publication->creationDatetime;

        $dto = new UserPublication();
        $dto->id = (int) $publication->id;
        $dto->title = $publication->title;
        $dto->slug = $publication->slug;
        $dto->creationDatetime = $creationDatetime;
        $dto->editionDatetime = $publication->editionDatetime;
        $dto->statusId = $publication->status;
        $dto->typeId = (int) $publication->type;
        $dto->language = $publication->language;
        $dto->translationLanguages = array_values(array_diff($groupLanguages, [$publication->language]));

        $subCategory = $publication->subCategory;
        $category = new UserPublicationCategory();
        $category->id = (int) $subCategory->id;
        $category->title = $this->referenceNames->subCategory($subCategory);
        $category->slug = $subCategory->slug;
        $dto->category = $category;

        $dto->coverUrl = $this->buildCoverUrl($publication);

        return $dto;
    }

    private function buildCoverUrl(Publication $publication): ?string
    {
        $cover = $publication->cover;
        if (!$cover instanceof \App\Entity\Image\PublicationCover) {
            return null;
        }

        $path = $this->uploaderHelper->asset($cover, 'imageFile');
        if ($path === null) {
            return null;
        }

        return $this->cacheManager->getBrowserPath($path, 'publication_cover_300x300');
    }

    /**
     * @param Publication[] $publications
     * @return UserPublication[]
     */
    public function buildFromEntities(array $publications): array
    {
        $translationGroups = array_filter(
            array_map(static fn (Publication $publication): ?string => $publication->translationGroup, $publications),
        );
        // One query for the whole page rather than one per translated publication.
        $languagesByGroup = $this->publicationRepository->findLanguagesByTranslationGroup(
            array_values(array_unique($translationGroups)),
        );

        return array_map(
            fn (Publication $publication): UserPublication => $this->buildFromEntity(
                $publication,
                $languagesByGroup[$publication->translationGroup ?? ''] ?? [],
            ),
            $publications,
        );
    }
}
