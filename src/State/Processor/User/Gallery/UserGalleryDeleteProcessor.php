<?php declare(strict_types=1);

namespace App\State\Processor\User\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Gallery;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<Gallery, null>
 */
readonly class UserGalleryDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var Gallery $data */
        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return null;
    }
}
