<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Service\ServiceAudio;
use App\Enum\Service\BeforeAfterSide;
use App\Http\StoredFileResponse;
use App\Security\Service\ServiceVisibilityChecker;
use App\Service\Audio\AudioTranscoder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * No byte ranges, which Safari wants before it plays an `<audio>` straight from a URL: the player fetches the whole
 * sound into a blob instead, as a voice note's does.
 *
 * @implements ProviderInterface<object>
 */
readonly class ServiceAudioStreamProvider implements ProviderInterface
{
    /** A new upload replaces the sound under the same address, so a cached copy must not outlive a visit by much. */
    private const int CACHE_MAX_AGE_SECONDS = 300;

    public function __construct(
        private ServiceVisibilityChecker $visibilityChecker,
        private StorageInterface $vichStorage,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StreamedResponse
    {
        $service = $this->visibilityChecker->checkVisible((string) $uriVariables['id']);
        $side = BeforeAfterSide::tryFrom((string) $uriVariables['side']);
        $audio = $side instanceof BeforeAfterSide ? $service->audioOn($side) : null;
        if (!$audio instanceof ServiceAudio) {
            throw new NotFoundHttpException('service.audio.not_found');
        }

        $response = StoredFileResponse::stream($this->vichStorage, $audio, 'uploadedFile', AudioTranscoder::OUTPUT_MIME_TYPE, $audio->side->value . '.m4a', $audio->size, inline: true);
        // Never in a shared cache: a draft, or a private profile, is its owner's alone.
        $response->setPrivate();
        $response->setMaxAge(self::CACHE_MAX_AGE_SECONDS);

        return $response;
    }
}
