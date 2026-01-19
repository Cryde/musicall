<?php declare(strict_types=1);

namespace App\State\Processor\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Publication;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<mixed, null>
 */
class UserPublicationRemoveCoverProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicationRepository $publicationRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var Publication $publication */
        $publication = $this->publicationRepository->find($uriVariables['id']);

        $cover = $publication->getCover();
        if ($cover) {
            $publication->setCover(null);
            $this->entityManager->remove($cover);
            $this->entityManager->flush();
        }

        return null;
    }
}
