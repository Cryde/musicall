<?php declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Entity\Musician\MusicianProfile;
use App\Repository\Musician\MusicianProfileRepository;
use App\Service\Bot\PageMetaDataProviderInterface;
use App\Translation\ReferenceNameTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class MusicianProfileMetaDataProvider implements PageMetaDataProviderInterface
{
    private const string PATTERN = '#^/u/([^/]+)/musician$#';

    public function __construct(
        private MusicianProfileRepository $musicianProfileRepository,
        private ProfileMeta $profileMeta,
        private TranslatorInterface $translator,
        private ReferenceNameTranslator $referenceNameTranslator,
    ) {
    }

    public function supports(string $uri): bool
    {
        return (bool) preg_match(self::PATTERN, $uri);
    }

    public function getMetaData(string $uri): array
    {
        if (!preg_match(self::PATTERN, $uri, $matches)) {
            return [];
        }

        $musicianProfile = $this->musicianProfileRepository->findByUsername($matches[1]);
        if (!$musicianProfile instanceof MusicianProfile) {
            return $this->profileMeta->missing();
        }

        $user = $musicianProfile->user;
        if (($withheld = $this->profileMeta->withheld($user)) !== null) {
            return $withheld;
        }

        $name = $this->profileMeta->name($user);
        $instruments = [];
        foreach ($musicianProfile->instruments as $instrument) {
            $instruments[] = $this->referenceNameTranslator->instrument($instrument->instrument);
        }

        return [
            'title' => $this->translator->trans('meta.musician.title', ['name' => $name]),
            'description' => $instruments === []
                ? $this->translator->trans('meta.musician.description', ['name' => $name])
                : $this->translator->trans('meta.musician.description_instruments', ['name' => $name, 'instruments' => implode(', ', $instruments)]),
            'cover' => $this->profileMeta->picture($user),
            'json_ld' => ['@context' => 'https://schema.org', ...$this->profileMeta->person($user, $instruments)],
        ];
    }
}
