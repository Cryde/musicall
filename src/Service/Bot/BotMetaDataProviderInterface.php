<?php declare(strict_types=1);

namespace App\Service\Bot;

interface BotMetaDataProviderInterface
{
    public function supports(string $uri): bool;

    /**
     * `robots` overrides « index, follow »; `json_ld` is a schema.org object, rendered escaped in the page.
     *
     * @return array{title?: string, description?: string, cover?: string|null, robots?: string, json_ld?: array<string, mixed>}
     */
    public function getMetaData(string $uri): array;
}
