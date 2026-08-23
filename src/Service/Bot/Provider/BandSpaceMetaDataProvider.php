<?php declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Service\Bot\BotMetaDataProviderInterface;

/**
 * Metadata for the public Band Space presentation page.
 *
 * Static, unlike its siblings: the others resolve a slug to a publication, a gallery or a profile,
 * whereas this is one fixed marketing page with nothing to look up.
 *
 * It buys a correct title and description when somebody shares the page. It does not make the page
 * indexable: bot_base.html.twig renders these tags over an empty `<div id="app">`, so a crawler gets
 * no body text either way. Ranking needs server side rendering, not a provider.
 *
 * No `cover`, so the template falls back to the site logo. Pointing it at one of the in-page
 * screenshots meant resolving a Vite hashed path through the asset packages, which reads
 * public/build/entrypoints.json, a build artefact the PHP test job does not produce. A share card
 * showing the product is still worth having, but it needs a purpose built image served as a static
 * file rather than a bundled asset.
 */
readonly class BandSpaceMetaDataProvider implements BotMetaDataProviderInterface
{
    private const string URI = '/band-space';

    public function supports(string $uri): bool
    {
        return rtrim($uri, '/') === self::URI;
    }

    /**
     * @return array{title?: string, description?: string, cover?: string|null}
     */
    public function getMetaData(string $uri): array
    {
        return [
            'title' => 'Band Space, l\'espace de travail de votre groupe - MusicAll',
            'description' => 'Réunissez l\'agenda, les tâches, les notes, les setlists, les fichiers '
                . 'et les finances de votre groupe dans un espace partagé. Gratuit avec votre compte MusicAll.',
        ];
    }
}
