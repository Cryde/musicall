<?php

declare(strict_types=1);

namespace App\State\Processor\Admin\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Publication\Tag;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<Tag, null>
 */
readonly class AdminTagDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var Tag $data */
        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return null;
    }
}
