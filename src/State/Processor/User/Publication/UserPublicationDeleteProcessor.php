<?php declare(strict_types=1);

namespace App\State\Processor\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Publication\UserPublication;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<UserPublication, void>
 */
class UserPublicationDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicationRepository $publicationRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var UserPublication $data */
        $publication = $this->publicationRepository->find($data->id);

        if ($publication) {
            $this->entityManager->remove($publication);
            $this->entityManager->flush();
        }
    }
}
