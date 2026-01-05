<?php declare(strict_types=1);

namespace App\Service\Bot;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class BotMetaDataGenerator
{
    /**
     * @param iterable<BotMetaDataProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.bot_metadata_provider')]
        private iterable $providers,
    ) {
    }

    /**
     * @return array{title?: string, description?: string, cover?: string|null}
     */
    public function getMetaData(string $uri): array
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($uri)) {
                return $provider->getMetaData($uri);
            }
        }

        return [];
    }
}
