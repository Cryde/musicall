<?php declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Bot\PageMetaDataProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class UserProfileMetaDataProvider implements PageMetaDataProviderInterface
{
    private const string PATTERN = '#^/u/([^/]+)$#';

    public function __construct(
        private UserRepository $userRepository,
        private ProfileMeta $profileMeta,
        private TranslatorInterface $translator,
    ) {
    }

    public function supports(string $uri): bool
    {
        // /u/{username}/teacher has its own provider; /musician answers a 301 to this page (#1317).
        return (bool) preg_match(self::PATTERN, $uri);
    }

    public function getMetaData(string $uri): array
    {
        if (!preg_match(self::PATTERN, $uri, $matches)) {
            return [];
        }

        $user = $this->userRepository->findOneBy(['username' => $matches[1]]);
        if (!$user instanceof User) {
            return $this->profileMeta->missing();
        }
        if (($withheld = $this->profileMeta->withheld($user)) !== null) {
            return $withheld;
        }

        $name = $this->profileMeta->name($user);
        $bio = $user->profile->bio;

        return [
            'title' => $this->translator->trans('meta.profile.title', ['name' => $name]),
            'description' => $bio
                ? $this->profileMeta->shorten($bio)
                : $this->translator->trans('meta.profile.description', ['name' => $name]),
            'cover' => $this->profileMeta->picture($user),
            'json_ld' => ['@context' => 'https://schema.org', ...$this->profileMeta->person($user)],
        ];
    }
}
