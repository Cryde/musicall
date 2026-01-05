<?php

declare(strict_types=1);

namespace App\Service\Finder\Musician;

use App\ApiResource\Search\AnnounceMusicianFilter;
use App\Exception\Musician\InvalidResultException;
use App\Exception\Musician\NoResultException;
use App\Repository\Attribute\InstrumentRepository;
use App\Repository\Attribute\StyleRepository;
use App\Service\Finder\Musician\Builder\AnnounceMusicianFilterBuilder;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class MusicianFilterGenerator
{
    public function __construct(
        private InstrumentRepository          $instrumentRepository,
        private StyleRepository               $styleRepository,
        #[Autowire(service: 'app.ai.agent.musician_filter')]
        private AgentInterface                $agent,
        private AnnounceMusicianFilterBuilder $announceMusicianFilterBuilder,
    ) {
    }

    public function find(string $search): ?AnnounceMusicianFilter
    {
        $messages = new MessageBag(
            Message::forSystem($this->getSystemPrompt()),
            Message::ofUser($search),
        );

        $result = $this->agent->call($messages, ['response_format' => $this->getResponseFormat()]);

        $content = $result->getContent();
        if (empty($content)) {
            throw new NoResultException('No result');
        }

        if (!is_array($content)) {
            throw new InvalidResultException('Invalid result');
        }

        return $this->announceMusicianFilterBuilder->buildFromArray($content);
    }

    private function getSystemPrompt(): string
    {
        return <<<PROMPT
            Tu es un assistant intelligent francophone qui va permettre de générer des filtres pour permettre aux utilisateurs de trouver des musiciens ou un groupe.

            L'assistant devra retourner le résultat sans aucun commentaire en format JSON RFC8259.

            Si l'utilisateur fournit une localisation, recherche ses coordonnées GPS (longitude et latitude). Si tu ne trouves pas la localisation, ne génère ni n'invente aucune coordonnée.

            Règles importantes :
            - Le champ "type" est obligatoire : utilise "2" si l'utilisateur cherche un musicien pour son groupe, utilise "1" si l'utilisateur cherche un groupe.
            - Le champ "instrument" est obligatoire : c'est l'id de l'instrument mentionné par l'utilisateur.
            - Le champ "styles" est optionnel : liste des ids des styles de musique mentionnés.
            - Le champ "coordinates" est optionnel : uniquement si une localisation est mentionnée et que tu peux trouver ses coordonnées GPS.

            Voici la liste des instruments disponibles (la clé est l'id et la valeur le slug) : {$this->getInstrumentIds()}

            Voici la liste des styles disponibles (la clé est l'id et la valeur le slug) : {$this->getStyleIds()}
            PROMPT;
    }

    /**
     * @return array{type: string, json_schema: array<string, mixed>}
     */
    private function getResponseFormat(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'musician_filter',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'instrument' => [
                            'type' => 'string',
                            'description' => 'The id of the instrument in users input.',
                        ],
                        'type' => [
                            'type' => 'string',
                            'description' => 'Si l\'utilisateur cherche un musicien pour son groupe (ou un musicien tout simplement) utiliser la valeur 2. Si l\'utilisateur cherche un groupe utiliser la valeur 1.',
                            'enum' => ['1', '2'],
                        ],
                        'styles' => [
                            'type' => 'array',
                            'description' => 'Seulement si tu as trouvé quelque chose sur les styles de musique dans les users input.',
                            'items' => [
                                'type' => 'string',
                                'description' => 'L\'id du style de musique.',
                            ],
                        ],
                        'coordinates' => [
                            'type' => ['object', 'null'],
                            'description' => 'La latitude et la longitude de la localisation que l\'utilisateur a mentionné (seulement s\'il y en a une).',
                            'properties' => [
                                'longitude' => [
                                    'type' => ['number', 'null'],
                                    'description' => 'Longitude de la localisation.',
                                ],
                                'latitude' => [
                                    'type' => ['number', 'null'],
                                    'description' => 'Latitude de la localisation.',
                                ],
                            ],
                            'required' => ['longitude', 'latitude'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'required' => ['type', 'instrument', 'styles', 'coordinates'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function getInstrumentIds(): string
    {
        $instruments = $this->instrumentRepository->findAll();
        $arrayInstruments = [];
        foreach ($instruments as $instrument) {
            $arrayInstruments[$instrument->getId()] = $instrument->getSlug();
        }

        return json_encode($arrayInstruments);
    }

    private function getStyleIds(): string
    {
        $styles = $this->styleRepository->findAll();
        $arrayStyles = [];
        foreach ($styles as $style) {
            $arrayStyles[$style->getId()] = $style->getSlug();
        }

        return json_encode($arrayStyles);
    }
}
