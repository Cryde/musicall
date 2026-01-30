<?php

declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Repository\Teacher\TeacherProfileRepository;
use App\Service\Bot\BotMetaDataProviderInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class TeacherProfileMetaDataProvider implements BotMetaDataProviderInterface
{
    public function __construct(
        private TeacherProfileRepository $teacherProfileRepository,
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
    ) {
    }

    public function supports(string $uri): bool
    {
        return (bool) preg_match('#^/u/[^/]+/teacher$#', $uri);
    }

    public function getMetaData(string $uri): array
    {
        if (!preg_match('#^/u/([^/]+)/teacher$#', $uri, $matches)) {
            return [];
        }

        $teacherProfile = $this->teacherProfileRepository->findByUsername($matches[1]);

        if (!$teacherProfile) {
            return [];
        }

        $user = $teacherProfile->getUser();
        $username = $user->getUsername();

        $instruments = $teacherProfile->getInstruments();
        $instrumentNames = [];
        foreach ($instruments as $instrument) {
            $instrumentNames[] = $instrument->getInstrument()->getName();
        }

        $description = sprintf('Découvrez le profil professeur de %s sur MusicAll.', $username);
        if (count($instrumentNames) > 0) {
            $description = sprintf(
                'Découvrez le profil professeur de %s sur MusicAll. Enseigne : %s.',
                $username,
                implode(', ', $instrumentNames)
            );
        }

        $cover = null;
        $profilePicture = $user->getProfilePicture();
        if ($profilePicture) {
            $path = $this->uploaderHelper->asset($profilePicture, 'imageFile');
            if ($path !== null) {
                $cover = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_large');
            }
        }

        return [
            'title' => sprintf('Profil professeur de %s - MusicAll', $username),
            'description' => $description,
            'cover' => $cover,
        ];
    }
}
