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
     * For the share bots: every page that has a provider.
     *
     * @return array{title?: string, description?: string, cover?: string|null, robots?: string, json_ld?: array<string, mixed>}
     */
    public function getMetaData(string $uri): array
    {
        return $this->find($uri, false);
    }

    /**
     * For the page served to everyone: only the pages whose provider is ready for it (PageMetaDataProviderInterface).
     *
     * @return array{title?: string, description?: string, cover?: string|null, robots?: string, json_ld?: array<string, mixed>}
     */
    public function getPageMetaData(string $path): array
    {
        return $this->find($path, true);
    }

    /**
     * @return array{title?: string, description?: string, cover?: string|null, robots?: string, json_ld?: array<string, mixed>}
     */
    private function find(string $uri, bool $pageOnly): array
    {
        $uri = $this->inDefaultLocale($uri);
        foreach ($this->providers as $provider) {
            if ($pageOnly && !$provider instanceof PageMetaDataProviderInterface) {
                continue;
            }
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
