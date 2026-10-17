<?php declare(strict_types=1);

namespace App\Service\Bot;

use App\Locale\LocalizedPath;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class BotMetaDataGenerator
{
    /**
     * @param iterable<BotMetaDataProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.bot_metadata_provider')]
        private iterable $providers,
        private LocalizedPath $localizedPath,
    ) {
    }

    /**
     * @return array{title?: string, description?: string, cover?: string|null}
     */
    public function getMetaData(string $uri): array
    {
        $uri = $this->inDefaultLocale($uri);
        foreach ($this->providers as $provider) {
            if ($provider->supports($uri)) {
                return $provider->getMetaData($uri);
            }
        }

        return [];
    }

    /**
     * The providers match the default locale's paths, so `/en/lessons/x?a=b` reads as `/cours/x?a=b`.
     */
    private function inDefaultLocale(string $uri): string
    {
        $path = explode('?', $uri, 2)[0];
        $query = strstr($uri, '?');

        return $this->localizedPath->inDefaultLocale($path) . ($query === false ? '' : $query);
    }
}
