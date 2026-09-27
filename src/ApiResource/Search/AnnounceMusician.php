<?php declare(strict_types=1);

namespace App\ApiResource\Search;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\State\ParameterProvider\ReadLinkParameterProvider;
use App\ApiResource\Search\Result\Instrument;
use App\ApiResource\Search\Result\Style;
use App\ApiResource\Search\Result\User;
use App\Entity\Attribute\Instrument as InstrumentEntity;
use App\Entity\Attribute\Style as StyleEntity;
use App\Entity\Musician\MusicianAnnounce;
use App\State\Provider\Search\MusicianSearchProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[GetCollection(
    uriTemplate: 'musicians/search',
    openapi: new Operation(tags: ['Musician announce']),
    paginationEnabled: false,
    name: 'api_musician_announces_search_collection',
    provider: MusicianSearchProvider::class,
    parameters: [
        'type'       => new QueryParameter(
            key: 'type',
            schema: ['enum' => [MusicianAnnounce::TYPE_MUSICIAN_STR, MusicianAnnounce::TYPE_BAND_STR]],
            description: 'Either a musician or a band you want to search (optional)',
            required: false,
        ),
        'instrument' => new QueryParameter(
            key: 'instrument',
            provider: ReadLinkParameterProvider::class,
            description: 'The instrument you want to search (optional)',
            required: false,
            extraProperties: ['resource_class' => InstrumentEntity::class],
        ),
        'styles' => new QueryParameter(
            key: 'styles',
            provider: ReadLinkParameterProvider::class,
            description: 'The style you want to search',
            extraProperties: ['resource_class' => StyleEntity::class],
        ),
        'latitude' => new QueryParameter(
            key: 'latitude',
            schema: ['type' => 'number', 'format' => 'float'],
            description: 'The latitude coordinate for location-based search',
        ),
        'longitude' => new QueryParameter(
            key: 'longitude',
            schema: ['type' => 'number', 'format' => 'float'],
            description: 'The longitude coordinate for location-based search',
        ),
        // Only around the coordinates, when both are given (#1084).
        'radius' => new QueryParameter(
            key: 'radius',
            schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 500],
            description: 'The distance in km around the coordinates to search within (optional)',
            constraints: [new Assert\Sequentially([
                new Assert\Regex(pattern: '/^\d+\z/', message: 'La distance doit être un nombre entier de kilomètres'),
                new Assert\Range(min: 1, max: 500, notInRangeMessage: 'La distance doit être comprise entre {{ min }} et {{ max }} km'),
            ])],
        ),
        // Only recorded, for the frequent searches (#1075): the search itself goes by the coordinates.
        'location' => new QueryParameter(
            key: 'location',
            description: 'The name of the city the coordinates were picked from (optional)',
            constraints: [new Assert\Length(max: 255)],
        ),
        // Set by a landing page's list on arrival, which is not a search anybody asked for (#1075).
        'landing' => new QueryParameter(
            key: 'landing',
            description: 'Set to 1 when the list is a landing page opening, not a search (optional)',
            constraints: [new Assert\Choice(choices: ['1'])],
        ),
        'page' => new QueryParameter(
            key: 'page',
            schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            description: 'Page number for pagination',
        ),
    ],
)]
class AnnounceMusician
{
    public string $id;
    public ?string $locationName = null;
    public ?string $note = null;
    #[ApiProperty(genId: false)]
    public User $user;
    #[ApiProperty(genId: false)]
    public Instrument $instrument;
    public int $type;
    /** @var Style[] */
    #[ApiProperty(genId: false)]
    public $styles;
    public ?float $distance = null;
}
