<?php declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Repository\UserRepository;
use App\Service\Bot\BotMetaDataProviderInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class UserProfileMetaDataProvider implements BotMetaDataProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private UploaderHelper $uploaderHelper,
        private CacheManager   $cacheManager,
    ) {
    }

    public function supports(string $uri): bool
    {
        // Match /u/{username} but not /u/{username}/musician (handled by MusicianProfileMetaDataProvider)
        return (bool) preg_match('#^/u/[^/]+$#', $uri);
    }

    public function getMetaData(string $uri): array
    {
        if (!preg_match('#^/u/([^/]+)$#', $uri, $matches)) {
            return [];
        }

        $user = $this->userRepository->findOneBy(['username' => $matches[1]]);

        if (!$user) {
            return [];
        }

        $userProfile = $user->getProfile();

        // Check if profile is public
        if (!$userProfile->isPublic()) {
            return [
                'title' => 'Profil privé - MusicAll',
                'description' => 'Ce profil est privé.',
            ];
        }

        $username = $user->getUsername();
        $displayName = $userProfile->getDisplayName() ?: $username;

        $description = sprintf('Découvrez le profil de %s sur MusicAll.', $displayName);
        if ($userProfile->getBio()) {
            $bio = $userProfile->getBio();
            $description = mb_strlen($bio) > 150 ? mb_substr($bio, 0, 147) . '...' : $bio;
        }

        $cover = null;
        $profilePicture = $user->getProfilePicture();
        if ($profilePicture && $path = $this->uploaderHelper->asset($profilePicture, 'imageFile')) {
            $cover = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_large');
        }

        return [
            'title' => sprintf('%s - MusicAll', $displayName),
            'description' => $description,
            'cover' => $cover,
        ];
    }
}
