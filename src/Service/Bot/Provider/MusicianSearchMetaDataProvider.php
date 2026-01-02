<?php declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Service\Bot\BotMetaDataProviderInterface;

readonly class MusicianSearchMetaDataProvider implements BotMetaDataProviderInterface
{
    public function supports(string $uri): bool
    {
        return $uri === '/rechercher-un-musicien' || $uri === '/rechercher-un-musicien/';
    }

    public function getMetaData(string $uri): array
    {
        return [
            'title' => 'Rechercher un musicien - MusicAll',
            'description' => 'Trouvez des musiciens près de chez vous : guitaristes, batteurs, bassistes, chanteurs... Rejoignez un groupe ou formez le vôtre !',
        ];
    }
}
