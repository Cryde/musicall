<?php

declare(strict_types=1);

namespace App\State\Processor\Admin\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Publication;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class AdminPublicationRejectProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PublicationRepository $publicationRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$publication = $this->publicationRepository->find($uriVariables['id'])) {
            throw new NotFoundHttpException('Publication not found');
        }

        $publication->setStatus(Publication::STATUS_DRAFT);
        $this->entityManager->flush();

        return null;
    }
}
