<?php

declare(strict_types=1);

namespace App\Service\Builder\Publication;

use App\ApiResource\Course\CourseCategory;
use App\ApiResource\Publication\PublicationCategory;
use App\Entity\PublicationSubCategory;

readonly class PublicationCategoryBuilder
{
    public function buildPublicationCategory(PublicationSubCategory $entity): PublicationCategory
    {
        $dto = new PublicationCategory();
        $dto->id = $entity->getId();
        $dto->title = $entity->getTitle();
        $dto->slug = $entity->getSlug();
        $dto->position = $entity->getPosition();

        return $dto;
    }

    /**
     * @param PublicationSubCategory[] $entities
     *
     * @return PublicationCategory[]
     */
    public function buildPublicationCategories(array $entities): array
    {
        return array_map(fn (PublicationSubCategory $entity) => $this->buildPublicationCategory($entity), $entities);
    }

    public function buildCourseCategory(PublicationSubCategory $entity): CourseCategory
    {
        $dto = new CourseCategory();
        $dto->id = $entity->getId();
        $dto->title = $entity->getTitle();
        $dto->slug = $entity->getSlug();
        $dto->position = $entity->getPosition();

        return $dto;
    }

    /**
     * @param PublicationSubCategory[] $entities
     *
     * @return CourseCategory[]
     */
    public function buildCourseCategories(array $entities): array
    {
        return array_map(fn (PublicationSubCategory $entity) => $this->buildCourseCategory($entity), $entities);
    }
}
