<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use App\Repository\PublicationRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<Publication>
 */
readonly class AdminPublicationActionProvider implements ProviderInterface
{
    public function __construct(
        private PublicationRepository $publicationRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Publication
    {
        $publication = $this->publicationRepository->find($uriVariables['id']);

        if (!$publication) {
            throw new NotFoundHttpException('Publication not found');
        }

        return $publication;
    }
}
