<?php declare(strict_types=1);

namespace App\State\Processor\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<mixed, void>
 */
readonly class AnnounceDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
       $this->entityManager->remove($data);
       $this->entityManager->flush();
    }
}
