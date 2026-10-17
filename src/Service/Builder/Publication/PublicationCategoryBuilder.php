<?php

declare(strict_types=1);

namespace App\Service\Builder\Publication;

use App\ApiResource\Course\CourseCategory;
use App\ApiResource\Publication\PublicationCategory;
use App\Entity\PublicationSubCategory;
use App\Translation\ReferenceNameTranslator;

readonly class PublicationCategoryBuilder
{
    public function __construct(
        private ReferenceNameTranslator $referenceNames,
    ) {
    }

    public function buildPublicationCategory(PublicationSubCategory $entity): PublicationCategory
    {
        $dto = new PublicationCategory();
        $dto->id = (int) $entity->id;
        $dto->title = $this->referenceNames->subCategory($entity);
        $dto->slug = $entity->slug;
        $dto->position = (int) $entity->position;

        return $dto;
    }

    /**
     * @param PublicationSubCategory[] $entities
     *
     * @return PublicationCategory[]
     */
    public function buildPublicationCategories(array $entities): array
    {
        return array_map(fn (PublicationSubCategory $entity): \App\ApiResource\Publication\PublicationCategory => $this->buildPublicationCategory($entity), $entities);
    }

    public function buildCourseCategory(PublicationSubCategory $entity): CourseCategory
    {
        $dto = new CourseCategory();
        $dto->id = (int) $entity->id;
        $dto->title = $this->referenceNames->subCategory($entity);
        $dto->slug = $entity->slug;
        $dto->position = (int) $entity->position;

        return $dto;
    }

    /**
     * @param PublicationSubCategory[] $entities
     *
     * @return CourseCategory[]
     */
    public function buildCourseCategories(array $entities): array
    {
        return array_map(fn (PublicationSubCategory $entity): \App\ApiResource\Course\CourseCategory => $this->buildCourseCategory($entity), $entities);
    }
}
