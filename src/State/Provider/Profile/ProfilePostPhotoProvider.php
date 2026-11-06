<?php

declare(strict_types=1);

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Profile\ProfilePostImage;
use App\Entity\User;
use App\Http\StoredFileResponse;
use App\Security\Profile\ProfileWallAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * @implements ProviderInterface<StreamedResponse>
 */
readonly class ProfilePostPhotoProvider implements ProviderInterface
{
    private const int CACHE_MAX_AGE_SECONDS = 86400;

    public function __construct(
        private ProfileWallAccess $wallAccess,
        private EntityManagerInterface $entityManager,
        private StorageInterface $vichStorage,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StreamedResponse
    {
        $owner = $this->wallAccess->owner((string) $uriVariables['username']);
        $viewer = $this->security->getUser();
        $image = $this->entityManager->find(ProfilePostImage::class, (string) $uriVariables['id']);
        if (
            !$image instanceof ProfilePostImage
            || $image->post->id !== (string) $uriVariables['postId']
            || $image->post->author->id !== $owner->id
            || $this->wallAccess->isHiddenFrom($owner, $viewer instanceof User ? $viewer : null)
        ) {
            throw new NotFoundHttpException('profile.wall.photo_not_found');
        }

        $response = StoredFileResponse::stream(
            $this->vichStorage,
            $image,
            'uploadedFile',
            $image->mimeType,
            $image->originalName,
            $image->size,
            inline: true,
        );
        $response->setPrivate();
        $response->setMaxAge(self::CACHE_MAX_AGE_SECONDS);

        return $response;
    }
}
