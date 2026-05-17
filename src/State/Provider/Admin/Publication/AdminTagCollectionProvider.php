<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Publication\AdminTag;
use App\Repository\Publication\TagRepository;

/**
 * @implements ProviderInterface<AdminTag>
 */
readonly class AdminTagCollectionProvider implements ProviderInterface
{
    public function __construct(
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * @return AdminTag[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(
            static function (array $row): AdminTag {
                $tag = $row['tag'];
                $dto = new AdminTag();
                $dto->id = (int) $tag->id;
                $dto->label = $tag->label;
                $dto->slug = $tag->slug;
                $dto->creationDatetime = $tag->creationDatetime;
                $dto->publicationCount = $row['count'];

                return $dto;
            },
            $this->tagRepository->findAllWithPublicationCount()
        );
    }
}
