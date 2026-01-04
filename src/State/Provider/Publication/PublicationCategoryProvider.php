<?php

declare(strict_types=1);

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Publication\PublicationCategory;
use App\Entity\PublicationSubCategory;
use App\Repository\PublicationSubCategoryRepository;
use App\Service\Builder\Publication\PublicationCategoryBuilder;

/**
 * @implements ProviderInterface<PublicationCategory>
 */
readonly class PublicationCategoryProvider implements ProviderInterface
{
    public function __construct(
        private PublicationSubCategoryRepository $publicationSubCategoryRepository,
        private PublicationCategoryBuilder $publicationCategoryBuilder,
    ) {
    }

    /**
     * @return PublicationCategory[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $categories = $this->publicationSubCategoryRepository->findByTypeOrderedByPosition(
            PublicationSubCategory::TYPE_PUBLICATION
        );

        return $this->publicationCategoryBuilder->buildPublicationCategories($categories);
    }
}
