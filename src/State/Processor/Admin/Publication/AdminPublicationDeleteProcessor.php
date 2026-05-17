<?php

declare(strict_types=1);

namespace App\State\Processor\Admin\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Publication;
use App\Procedure\Publication\PublicationDeleteProcedure;
use App\Repository\PublicationRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class AdminPublicationDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private PublicationRepository       $publicationRepository,
        private PublicationDeleteProcedure  $publicationDeleteProcedure,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $publication = $this->publicationRepository->find($uriVariables['id']);
        if (!$publication instanceof Publication) {
            throw new NotFoundHttpException('Publication not found');
        }

        $this->publicationDeleteProcedure->delete($publication);

        return null;
    }
}
