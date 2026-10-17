<?php

declare(strict_types=1);

namespace App\State\Provider\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Forum\ForumCategory;
use App\Repository\Forum\ForumCategoryRepository;
use App\Service\Builder\Forum\ForumCategoryListBuilder;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * @implements ProviderInterface<ForumCategory>
 */
readonly class ForumCategoryProvider implements ProviderInterface
{
    private const string DEFAULT_SOURCE_SLUG = 'root';

    public function __construct(
        private ForumCategoryRepository   $forumCategoryRepository,
        private ForumCategoryListBuilder  $forumCategoryListBuilder,
        private LocaleSwitcher            $localeSwitcher,
    ) {
    }

    /**
     * The forums of the request's locale only: categories are per language rows (#1188).
     *
     * @return ForumCategory[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $categories = $this->forumCategoryRepository->findBySourceSlugAndLocaleOrderedByPosition(
            self::DEFAULT_SOURCE_SLUG,
            $this->localeSwitcher->getLocale(),
        );

        return $this->forumCategoryListBuilder->buildFromEntities($categories);
    }
}
