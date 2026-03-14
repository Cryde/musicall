<?php declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Repository\Musician\MusicianProfileRepository;
use App\Service\Bot\BotMetaDataProviderInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class MusicianProfileMetaDataProvider implements BotMetaDataProviderInterface
{
    public function __construct(
        private MusicianProfileRepository $musicianProfileRepository,
        private UploaderHelper            $uploaderHelper,
        private CacheManager              $cacheManager,
    ) {
    }

    public function supports(string $uri): bool
    {
        return (bool) preg_match('#^/u/[^/]+/musician$#', $uri);
    }

    public function getMetaData(string $uri): array
    {
        if (!preg_match('#^/u/([^/]+)/musician$#', $uri, $matches)) {
            return [];
        }

        $musicianProfile = $this->musicianProfileRepository->findByUsername($matches[1]);

        if (!$musicianProfile) {
            return [];
        }

        $user = $musicianProfile->user;
        $username = $user->username;

        $instrumentNames = [];
        foreach ($musicianProfile->instruments as $instrument) {
            $instrumentNames[] = $instrument->instrument->name;
        }

        $description = sprintf('Découvrez le profil musicien de %s sur MusicAll.', $username);
        if (count($instrumentNames) > 0) {
            $description = sprintf(
                'Découvrez le profil musicien de %s sur MusicAll. Instruments : %s.',
                $username,
                implode(', ', $instrumentNames)
            );
        }

        $cover = null;
        $profilePicture = $user->profilePicture;
        if ($profilePicture && $path = $this->uploaderHelper->asset($profilePicture, 'imageFile')) {
            $cover = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_large');
        }

        return [
            'title' => sprintf('Profil musicien de %s - MusicAll', $username),
            'description' => $description,
            'cover' => $cover,
        ];
    }
}
