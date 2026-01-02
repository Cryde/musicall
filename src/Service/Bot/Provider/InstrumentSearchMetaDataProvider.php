<?php declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Service\Bot\BotMetaDataProviderInterface;

readonly class InstrumentSearchMetaDataProvider implements BotMetaDataProviderInterface
{
    private const array INSTRUMENT_CONFIG = [
        'guitariste' => [
            'title' => 'Rechercher un guitariste - MusicAll',
            'description' => 'Trouvez un guitariste près de chez vous pour rejoindre votre groupe ou collaborer sur vos projets musicaux.',
        ],
        'batteur' => [
            'title' => 'Rechercher un batteur - MusicAll',
            'description' => 'Trouvez un batteur près de chez vous pour rejoindre votre groupe ou collaborer sur vos projets musicaux.',
        ],
        'bassiste' => [
            'title' => 'Rechercher un bassiste - MusicAll',
            'description' => 'Trouvez un bassiste près de chez vous pour rejoindre votre groupe ou collaborer sur vos projets musicaux.',
        ],
        'chanteur' => [
            'title' => 'Rechercher un chanteur - MusicAll',
            'description' => 'Trouvez un chanteur ou une chanteuse près de chez vous pour rejoindre votre groupe ou collaborer sur vos projets musicaux.',
        ],
        'pianiste' => [
            'title' => 'Rechercher un pianiste - MusicAll',
            'description' => 'Trouvez un pianiste près de chez vous pour rejoindre votre groupe ou collaborer sur vos projets musicaux.',
        ],
    ];

    public function supports(string $uri): bool
    {
        return $this->extractInstrument($uri) !== null;
    }

    public function getMetaData(string $uri): array
    {
        $instrument = $this->extractInstrument($uri);
        if ($instrument === null || !isset(self::INSTRUMENT_CONFIG[$instrument])) {
            return [];
        }

        return self::INSTRUMENT_CONFIG[$instrument];
    }

    private function extractInstrument(string $uri): ?string
    {
        $uri = rtrim($uri, '/');
        if (preg_match('#^/rechercher-un-(\w+)$#', $uri, $matches)) {
            $instrument = $matches[1];
            if (isset(self::INSTRUMENT_CONFIG[$instrument])) {
                return $instrument;
            }
        }

        return null;
    }
}
