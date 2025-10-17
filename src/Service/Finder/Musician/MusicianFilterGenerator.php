<?php declare(strict_types=1);

namespace App\Service\Finder\Musician;

use App\ApiResource\Search\AnnounceMusicianFilter;
use App\Exception\Musician\InvalidResultException;
use App\Exception\Musician\NoResultException;
use App\Repository\Attribute\InstrumentRepository;
use App\Repository\Attribute\StyleRepository;
use App\Service\Finder\Musician\Builder\AnnounceMusicianFilterBuilder;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class MusicianFilterGenerator
{
    public function __construct(
        private InstrumentRepository $instrumentRepository,
        private StyleRepository      $styleRepository,
        #[Autowire(service: 'ai.agent.announce.musician')]
        private Agent              $agent,
        private AnnounceMusicianFilterBuilder $announceMusicianFilterBuilder
    ) {
    }

    public function find(string $search): ?AnnounceMusicianFilter
    {
        $messages = $this->getSystemPrompt();
        $messages->add(Message::ofUser($search));
        $result = $this->agent->call($messages, $this->getOptions());
        if (!$toolCall = ($result->getContent()[0] ?? null)) {
            // improve this !
            throw new NoResultException('No result');
        }
        if (!$toolCall instanceof ToolCall) {
            throw new InvalidResultException('Invalid result');
        }

        return $this->announceMusicianFilterBuilder->buildFromArray($toolCall->getArguments());
    }

    private function getSystemPrompt(): MessageBag
    {
        return new MessageBag(
            Message::forSystem('Tu es un assistant intelligent francophone qui va permettre de générer des filtres pour permettre aux utilisateurs de trouver des musiciens ou un groupe.'),
            Message::forSystem('L\'assistant devra retourne le resultat sans aucun commentaire en format JSON RFC8259.'),
            Message::forSystem('Si l\'utilisateur fournit une localisation, recherche ses coordonnées GPS (longitude et latitude). Si tu ne trouves pas la localisation, ne génère ni n\'invente aucune coordonnée.'),
            Message::forSystem('Voici la liste des instruments disponibles (la clé est l\'id et la valeur le slug) : ' . $this->getInstrumentIds()),
            Message::forSystem('Voici la liste des style disponibles (la clé est l\'id et la valeur le slug) : ' . $this->getStyleIds()),
        );
    }

    private function getOptions(): array
    {
        return [
            'top_p' => 1,
            'presence_penalty' => 0,
            'frequency_penalty' => 0,
            'temperature' => 0.5,
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'extract_data',
                    'description' => 'Extract data from users input',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'instrument' => [
                                'type' => 'string',
                                'description' => 'The id of the instrument in users input.',
                            ],
                            'type' => [
                                'type' => 'string',
                                'description' => 'Si l\'utilisateur cherhche un musicien pour son groupe (ou un musicien tout simplemenet) utiliser la valeur 1. Si l\'utilisateur cherche un groupe utiliser la valeur 2.',
                                'enum' => ['1', '2'],
                            ],
                            'styles' => [
                                'type' => 'array',
                                'description' => 'Seulement si tu as trouvé quelque chose sur les styles de musique dans les users input.',
                                'items' => [
                                    'type' => 'string',
                                    'description' => 'L\'id du style de musique.',
                                ],
                                'additionalProperties' => false,
                            ],
                            'coordinates' => [
                                'type' => 'object',
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
                                'additionalProperties' => false,
                            ],
                        ],
                        'additionalProperties' => false,
                        'required' => ['type', 'instrument'],
                    ],
                ],
            ]],
            'tool_choice' => 'auto',
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
