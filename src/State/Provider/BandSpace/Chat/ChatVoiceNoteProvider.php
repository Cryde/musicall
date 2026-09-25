<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Service\BandSpace\Chat\ChatMediaResolver;
use App\Service\BandSpace\Chat\ChatVoiceNoteConverter;
use App\State\Provider\BandSpace\File\BandSpaceFileDownloadProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * No byte ranges, which Safari wants before it plays an `<audio>` straight from a URL: the player
 * fetches the whole note into a blob instead, a couple of megabytes at most.
 *
 * @implements ProviderInterface<object>
 */
readonly class ChatVoiceNoteProvider implements ProviderInterface
{
    public function __construct(
        private ChatMediaResolver $chatMediaResolver,
        private StorageInterface $vichStorage,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StreamedResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$file, $version] = $this->chatMediaResolver->resolve(
            (string) $uriVariables['bandSpaceId'],
            (string) $uriVariables['id'],
            $user,
            [ChatVoiceNoteConverter::OUTPUT_MIME_TYPE],
            'Note vocale introuvable',
        );

        $response = BandSpaceFileDownloadProvider::stream($file, $version, $this->vichStorage, inline: true);
        $response->setPrivate();
        $response->setMaxAge(ChatImageProvider::CACHE_MAX_AGE_SECONDS);

        return $response;
    }
}
