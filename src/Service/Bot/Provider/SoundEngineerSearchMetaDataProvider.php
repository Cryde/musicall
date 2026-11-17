<?php

declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Service\Bot\PageMetaDataProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The sound engineer search (#1410). The pages per speciality and city come with the annuaire (#1414), so a filtered
 * address says what the page itself says.
 */
readonly class SoundEngineerSearchMetaDataProvider implements PageMetaDataProviderInterface
{
    private const string PATH = '/rechercher-un-ingenieur-du-son';

    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function supports(string $uri): bool
    {
        return rtrim(explode('?', $uri, 2)[0], '/') === self::PATH;
    }

    public function getMetaData(string $uri): array
    {
        return [
            'title' => $this->translator->trans('meta.sound_engineer_search.title'),
            'description' => $this->translator->trans('meta.sound_engineer_search.description'),
        ];
    }
}
