<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication\Tag;
use App\Repository\Publication\TagRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<Tag>
 */
readonly class AdminTagItemProvider implements ProviderInterface
{
    public function __construct(
        private TagRepository $tagRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Tag
    {
        $tag = $this->tagRepository->find($uriVariables['id']);
        if (!$tag instanceof Tag) {
            throw new NotFoundHttpException('Tag not found');
        }

        return $tag;
    }
}
