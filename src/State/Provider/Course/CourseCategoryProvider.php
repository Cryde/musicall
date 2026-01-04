<?php

declare(strict_types=1);

namespace App\State\Provider\Course;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Course\CourseCategory;
use App\Entity\PublicationSubCategory;
use App\Repository\PublicationSubCategoryRepository;
use App\Service\Builder\Publication\PublicationCategoryBuilder;

/**
 * @implements ProviderInterface<CourseCategory>
 */
readonly class CourseCategoryProvider implements ProviderInterface
{
    public function __construct(
        private PublicationSubCategoryRepository $publicationSubCategoryRepository,
        private PublicationCategoryBuilder $publicationCategoryBuilder,
    ) {
    }

    /**
     * @return CourseCategory[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $categories = $this->publicationSubCategoryRepository->findByTypeOrderedByPosition(
            PublicationSubCategory::TYPE_COURSE
        );

        return $this->publicationCategoryBuilder->buildCourseCategories($categories);
    }
}
