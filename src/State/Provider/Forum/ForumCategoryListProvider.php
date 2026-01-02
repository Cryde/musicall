<?php

declare(strict_types=1);

namespace App\State\Provider\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Forum\ForumCategoryItem;
use App\Repository\Forum\ForumCategoryRepository;
use App\Service\Builder\Forum\ForumCategoryListBuilder;

/**
 * @implements ProviderInterface<ForumCategoryItem>
 */
readonly class ForumCategoryListProvider implements ProviderInterface
{
    private const string DEFAULT_SOURCE_SLUG = 'root';

    public function __construct(
        private ForumCategoryRepository   $forumCategoryRepository,
        private ForumCategoryListBuilder  $forumCategoryListBuilder,
    ) {
    }

    /**
     * @return ForumCategoryItem[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $categories = $this->forumCategoryRepository->findBySourceSlugOrderedByPosition(self::DEFAULT_SOURCE_SLUG);

        return $this->forumCategoryListBuilder->buildFromEntities($categories);
    }
}
