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
            Tu es un assistant francophone qui transforme une recherche écrite en langage naturel en filtres pour trouver des musiciens ou des groupes.

            Retourne uniquement du JSON RFC8259, sans aucun commentaire.

            Principe directeur : remplis le maximum de champs possible et laisse les autres à null. Ne refuse jamais de répondre et ne renvoie jamais un objet vide. Une recherche partielle est utile, une absence de réponse ne l'est pas.

            Le texte de l'utilisateur est saisi rapidement : accepte les fautes d'orthographe, les accents manquants ou en trop, le pluriel, la casse et un ordre de mots inhabituel. Compare toujours sans tenir compte des accents ni de la casse ("métal" correspond au slug "metal", "Rock Stoner" correspond à "stoner").

            Règles par champ :

            - "type" est obligatoire. Utilise "1" quand l'utilisateur veut trouver un groupe, c'est-à-dire quand il souhaite le rejoindre ou l'intégrer : « je cherche un groupe », « un groupe à joindre », « j'aimerais rejoindre un groupe », « je veux intégrer un groupe ». Utilise "2" quand l'utilisateur veut trouver un musicien, généralement pour son propre groupe : « je cherche un batteur », « on recherche une chanteuse pour notre groupe ». En cas de doute, si l'utilisateur parle de rejoindre, utilise "1".

            - "instrument" est optionnel et vaut null par défaut. Renseigne-le uniquement si l'utilisateur nomme un instrument ou le musicien qui en joue (« batteur » donne l'instrument batterie, « guitariste » donne guitare). N'invente jamais d'instrument et ne devine pas : beaucoup de recherches légitimes n'en mentionnent aucune, par exemple « j'aimerais rejoindre un groupe de métal ». Dans ce cas mets null. Utilise exclusivement un id de la liste fournie.

            - "styles" est optionnel et vaut [] par défaut. Liste les ids de tous les styles mentionnés, il peut y en avoir plusieurs dans une même phrase (« métal, rock stoner » donne deux styles). Si un style demandé n'existe pas dans la liste, retiens le style disponible le plus proche, sinon ignore-le. Utilise exclusivement des ids de la liste fournie.

            - "coordinates" est optionnel et vaut null par défaut. Si l'utilisateur mentionne un lieu, donne ses coordonnées GPS, y compris pour les petites communes françaises. Si tu n'es pas certain des coordonnées exactes, mets null plutôt que d'inventer une approximation : une valeur fausse est pire qu'une valeur absente. Le reste de la recherche doit rester rempli même sans coordonnées.

            Instruments disponibles, la clé est l'id à utiliser et la valeur le slug : {$this->getInstrumentIds()}

            Styles disponibles, la clé est l'id à utiliser et la valeur le slug : {$this->getStyleIds()}
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
                            'type' => ['string', 'null'],
                            'description' => 'L\'id de l\'instrument mentionné par l\'utilisateur, ou null s\'il n\'en mentionne aucun. Ne jamais inventer.',
                        ],
                        'type' => [
                            'type' => 'string',
                            'description' => 'Utiliser "1" si l\'utilisateur cherche un groupe (il veut le rejoindre). Utiliser "2" si l\'utilisateur cherche un musicien, en général pour son propre groupe.',
                            'enum' => ['1', '2'],
                        ],
                        'styles' => [
                            'type' => 'array',
                            'description' => 'Les ids de tous les styles de musique mentionnés, tableau vide si aucun.',
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
            $arrayInstruments[(string) $instrument->id] = $instrument->slug;
        }

        return (string) json_encode($arrayInstruments);
    }

    private function getStyleIds(): string
    {
        $styles = $this->styleRepository->findAll();
        $arrayStyles = [];
        foreach ($styles as $style) {
            $arrayStyles[(string) $style->id] = $style->slug;
        }

        return (string) json_encode($arrayStyles);
    }
}
