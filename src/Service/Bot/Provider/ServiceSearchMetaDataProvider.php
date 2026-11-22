<?php

declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Service\Bot\PageMetaDataProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The searches by speciality: sound engineers (#1410), photographers and videographers (#1411). The pages per
 * speciality and city come with the annuaire (#1414), so a filtered address says what the page itself says.
 */
readonly class ServiceSearchMetaDataProvider implements PageMetaDataProviderInterface
{
    /** Each search's path, and its title and description. */
    private const array PATHS = [
        '/rechercher-un-ingenieur-du-son' => ['meta.sound_engineer_search.title', 'meta.sound_engineer_search.description'],
        '/rechercher-un-photographe' => ['meta.photographer_search.title', 'meta.photographer_search.description'],
        '/rechercher-un-videaste' => ['meta.videographer_search.title', 'meta.videographer_search.description'],
    ];

    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function supports(string $uri): bool
    {
        return isset(self::PATHS[$this->path($uri)]);
    }

    public function getMetaData(string $uri): array
    {
        [$title, $description] = self::PATHS[$this->path($uri)];

        return [
            'title' => $this->translator->trans($title),
            'description' => $this->translator->trans($description),
        ];
    }

    private function path(string $uri): string
    {
        return rtrim(explode('?', $uri, 2)[0], '/');
    }
}
