<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Publication\AdminTag;
use App\Entity\Publication\Tag;
use App\Repository\Publication\TagRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<AdminTag>
 */
readonly class AdminTagItemReadProvider implements ProviderInterface
{
    public function __construct(
        private TagRepository $tagRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminTag
    {
        $tag = $this->tagRepository->find($uriVariables['id']);
        if (!$tag instanceof Tag) {
            throw new NotFoundHttpException('Tag not found');
        }

        $dto = new AdminTag();
        $dto->id = (int) $tag->id;
        $dto->label = $tag->label;
        $dto->slug = $tag->slug;
        $dto->creationDatetime = $tag->creationDatetime;
        $dto->publicationCount = $this->tagRepository->countPublicationsForTag((int) $tag->id);

        return $dto;
    }
}
