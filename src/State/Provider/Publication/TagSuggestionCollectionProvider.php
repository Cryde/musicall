<?php

declare(strict_types=1);

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Publication\TagSuggestion;
use App\Repository\Publication\TagRepository;

/**
 * @implements ProviderInterface<TagSuggestion>
 */
readonly class TagSuggestionCollectionProvider implements ProviderInterface
{
    private const int MAX_RESULTS = 15;

    public function __construct(
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * @return TagSuggestion[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];
        $term = is_string($filters['label'] ?? null) ? trim($filters['label']) : '';

        if ($term === '') {
            return [];
        }

        return array_map(
            static function ($tag): TagSuggestion {
                $dto = new TagSuggestion();
                $dto->slug = $tag->slug;
                $dto->label = $tag->label;

                return $dto;
            },
            $this->tagRepository->searchByLabel($term, self::MAX_RESULTS)
        );
    }
}
