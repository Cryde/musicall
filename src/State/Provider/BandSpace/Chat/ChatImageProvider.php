<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Service\BandSpace\Chat\ChatImageConverter;
use App\Service\BandSpace\Chat\ChatMediaResolver;
use App\State\Provider\BandSpace\File\BandSpaceFileDownloadProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * @implements ProviderInterface<object>
 */
readonly class ChatImageProvider implements ProviderInterface
{
    /** A day: a chat image does not change, but a member can still upload a new version from Files. */
    public const int CACHE_MAX_AGE_SECONDS = 86400;

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
            ChatImageConverter::ACCEPTED_MIME_TYPES,
            'band_space.chat.image_not_found',
        );

        $response = BandSpaceFileDownloadProvider::stream($file, $version, $this->vichStorage, inline: true);
        $response->setPrivate();
        $response->setMaxAge(self::CACHE_MAX_AGE_SECONDS);

        return $response;
    }
}
