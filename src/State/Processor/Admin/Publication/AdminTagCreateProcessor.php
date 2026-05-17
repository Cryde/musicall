<?php

declare(strict_types=1);

namespace App\State\Processor\Admin\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Admin\Publication\AdminTag;
use App\Repository\Publication\TagRepository;
use App\Service\Publication\TagService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<AdminTag, AdminTag>
 */
readonly class AdminTagCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private TagService             $tagService,
        private TagRepository          $tagRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminTag
    {
        /** @var AdminTag $data */
        $tags = $this->tagService->upsertByLabels([$data->label]);
        $this->entityManager->flush();

        $tag = $tags[0];
        $dto = new AdminTag();
        $dto->id = (int) $tag->id;
        $dto->label = $tag->label;
        $dto->slug = $tag->slug;
        $dto->creationDatetime = $tag->creationDatetime;
        $dto->publicationCount = $this->tagRepository->countPublicationsForTag((int) $tag->id);

        return $dto;
    }
}
