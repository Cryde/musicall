<?php declare(strict_types=1);

namespace App\Service\Bot;

interface BotMetaDataProviderInterface
{
    public function supports(string $uri): bool;

    /**
     * @return array{title?: string, description?: string, cover?: string|null}
     */
    public function getMetaData(string $uri): array;
}
