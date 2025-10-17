<?php declare(strict_types=1);

namespace App\Service\AI\Announce;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

class SystemPromptFactory
{
    public function __invoke(): MessageBag
    {
        return new MessageBag(
            Message::forSystem('Tu es un assistant intelligent francophone qui va permettre de générer des filtres pour permettre aux utilisateurs de trouver des musiciens ou un groupe.'),
            Message::forSystem('L\'assistant devra retourne le resultat sans aucun commentaire en format JSON RFC8259.'),
            Message::forSystem('Si l\'utilisateur rentre une localisation cherche les coordonnées (longitude & latitude), si tu ne trouve pas, n\'invente rien.'),
        );
    }
}


