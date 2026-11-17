<?php

declare(strict_types=1);

namespace App\State\Provider\Service\Work;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Service\ServiceWorkImage;
use App\Http\StoredFileResponse;
use App\Repository\Service\ServiceWorkImageRepository;
use App\Security\Service\ServiceVisibilityChecker;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * @implements ProviderInterface<object>
 */
readonly class ServiceWorkImageStreamProvider implements ProviderInterface
{
    /** A new upload is a new image under a new address, so a cached copy never goes stale. */
    private const int CACHE_MAX_AGE_SECONDS = 86400;

    public function __construct(
        private ServiceVisibilityChecker $visibilityChecker,
        private ServiceWorkImageRepository $imageRepository,
        private StorageInterface $vichStorage,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StreamedResponse
    {
        $service = $this->visibilityChecker->checkVisible((string) $uriVariables['serviceId']);
        $image = $this->imageRepository->findOneInWork((string) $service->id, (string) $uriVariables['workId'], (string) $uriVariables['id']);
        if (!$image instanceof ServiceWorkImage) {
            throw new NotFoundHttpException('service.work.image_not_found');
        }

        $response = StoredFileResponse::stream($this->vichStorage, $image, 'uploadedFile', $image->mimeType, $image->originalName, $image->size, inline: true);
        // Never in a shared cache: a draft, or a private profile, is its owner's alone.
        $response->setPrivate();
        $response->setMaxAge(self::CACHE_MAX_AGE_SECONDS);

        return $response;
    }
}
