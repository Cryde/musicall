<?php declare(strict_types=1);

namespace App\Service\Builder\User\Course;

use App\ApiResource\User\Course\UserCourse;
use App\ApiResource\User\Course\UserCourseCategory;
use App\Entity\Publication;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class UserCourseBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
    ) {
    }

    public function buildFromEntity(Publication $publication): UserCourse
    {
        $subCategory = $publication->getSubCategory();
        $creationDatetime = $publication->getCreationDatetime();

        $dto = new UserCourse();
        $dto->id = (int) $publication->getId();
        $dto->title = (string) $publication->getTitle();
        $dto->slug = (string) $publication->getSlug();
        $dto->creationDatetime = $creationDatetime;
        $dto->editionDatetime = $publication->getEditionDatetime();
        $dto->statusId = (int) $publication->getStatus();
        $dto->statusLabel = Publication::STATUS_LABEL[$publication->getStatus()] ?? 'Inconnu';
        $dto->typeId = (int) $publication->getType();
        $dto->typeLabel = $publication->getType() === Publication::TYPE_VIDEO
            ? Publication::TYPE_VIDEO_LABEL
            : Publication::TYPE_TEXT_LABEL;

        $category = new UserCourseCategory();
        $category->id = (int) $subCategory->getId();
        $category->title = (string) $subCategory->getTitle();
        $category->slug = (string) $subCategory->getSlug();
        $dto->category = $category;

        $dto->coverUrl = $this->buildCoverUrl($publication);

        return $dto;
    }

    private function buildCoverUrl(Publication $publication): ?string
    {
        $cover = $publication->getCover();
        if ($cover === null) {
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
     * @return UserCourse[]
     */
    public function buildFromEntities(array $publications): array
    {
        return array_map(fn(Publication $p) => $this->buildFromEntity($p), $publications);
    }
}
