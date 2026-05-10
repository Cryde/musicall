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
        $subCategory = $publication->subCategory;
        $creationDatetime = $publication->creationDatetime;

        $dto = new UserCourse();
        $dto->id = (int) $publication->id;
        $dto->title = $publication->title;
        $dto->slug = $publication->slug;
        $dto->creationDatetime = $creationDatetime;
        $dto->editionDatetime = $publication->editionDatetime;
        $dto->statusId = $publication->status;
        $dto->statusLabel = Publication::STATUS_LABEL[$publication->status] ?? 'Inconnu';
        $dto->typeId = (int) $publication->type;
        $dto->typeLabel = $publication->type === Publication::TYPE_VIDEO
            ? Publication::TYPE_VIDEO_LABEL
            : Publication::TYPE_TEXT_LABEL;

        $category = new UserCourseCategory();
        $category->id = (int) $subCategory->id;
        $category->title = $subCategory->title;
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
     * @return UserCourse[]
     */
    public function buildFromEntities(array $publications): array
    {
        return array_map(fn(Publication $p): \App\ApiResource\User\Course\UserCourse => $this->buildFromEntity($p), $publications);
    }
}
