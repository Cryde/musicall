<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Search;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Geocoding\CityLookup;
use App\ApiResource\Teacher\Public\TeacherProfileStyle;
use App\Entity\Attribute\Style;
use App\Enum\Service\SoundEngineeringSpeciality;
use App\State\ParameterProvider\UuidLinkParameterProvider;
use App\State\Provider\Service\SoundEngineerSearchProvider;
use Symfony\Component\Validator\Constraints as Assert;

/** The sound engineer search (#1410), over the shared query of ServiceRepository. */
#[GetCollection(
    uriTemplate: '/services/sound-engineering',
    openapi: new Operation(tags: ['Service']),
    paginationEnabled: false,
    name: 'api_services_sound_engineering_search',
    provider: SoundEngineerSearchProvider::class,
    parameters: [
        'speciality' => new QueryParameter(
            key: 'speciality',
            schema: ['type' => 'string', 'enum' => ['mixing', 'mastering', 'recording', 'live_recording', 'live_sound', 'editing']],
            description: 'What the sound engineer does (optional)',
            constraints: [new Assert\Choice(callback: [SoundEngineeringSpeciality::class, 'values'], message: 'search.sound_engineer.speciality.invalid')],
        ),
        'style' => new QueryParameter(
            key: 'style',
            provider: UuidLinkParameterProvider::class,
            description: 'A style the sound engineer works in (optional)',
            hydra: false,
            extraProperties: [
                'resource_class' => Style::class,
                'invalid_message' => 'search.style.invalid',
                'not_found_message' => 'search.style.not_found',
            ],
        ),
        'lat' => new QueryParameter(
            key: 'lat',
            schema: ['type' => 'number', 'minimum' => -90, 'maximum' => 90],
            description: 'The latitude of the searched point (optional, with lng)',
            constraints: [new Assert\Sequentially([
                new Assert\Regex(pattern: CityLookup::COORDINATE_PATTERN, message: 'search.coordinate.invalid'),
                new Assert\Range(min: -90, max: 90, notInRangeMessage: 'search.coordinate.invalid'),
            ])],
        ),
        'lng' => new QueryParameter(
            key: 'lng',
            schema: ['type' => 'number', 'minimum' => -180, 'maximum' => 180],
            description: 'The longitude of the searched point (optional, with lat)',
            constraints: [new Assert\Sequentially([
                new Assert\Regex(pattern: CityLookup::COORDINATE_PATTERN, message: 'search.coordinate.invalid'),
                new Assert\Range(min: -180, max: 180, notInRangeMessage: 'search.coordinate.invalid'),
            ])],
        ),
        // Only around the point, when lat and lng are given.
        'radius' => new QueryParameter(
            key: 'radius',
            schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 500],
            description: 'The distance in km around the point to search within (optional)',
            constraints: [new Assert\Sequentially([
                new Assert\Regex(pattern: '/^\d+\z/', message: 'search.radius.not_integer'),
                new Assert\Range(min: 1, max: 500, notInRangeMessage: 'search.radius.out_of_range'),
            ])],
        ),
        // Widens a search around a point: a sound engineer who works remotely counts wherever they are.
        'online' => new QueryParameter(
            key: 'online',
            schema: ['type' => 'string', 'enum' => ['0', '1']],
            description: 'Set to 1 to also find the sound engineers who work remotely, wherever they are (optional)',
            hydra: false,
            constraints: [new Assert\Choice(choices: ['0', '1'], message: 'search.flag.invalid')],
        ),
        'page' => new QueryParameter(
            key: 'page',
            schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            description: 'Page number for pagination',
            constraints: [new Assert\Sequentially([
                new Assert\Regex(pattern: '/^\d+\z/', message: 'search.page.invalid'),
                new Assert\Range(min: 1, max: self::MAX_PAGE, notInRangeMessage: 'search.page.invalid'),
            ])],
        ),
    ],
)]
// Not served, only the address of each member. Declared so a username may hold a dot.
#[NotExposed(
    uriTemplate: '/sound_engineer_search_results/{username}',
    requirements: ['username' => '[^/]+'],
)]
class SoundEngineerSearchResult
{
    public const int PAGE_SIZE = 12;
    private const int MAX_PAGE = 1000;

    #[ApiProperty(identifier: true)]
    public string $username;

    /** What the site calls the member: their display name, or their username. */
    public string $name;

    public ?string $profilePictureUrl = null;

    public ?string $headline = null;

    /**
     * SoundEngineeringSpeciality values, the searched one first.
     *
     * @var list<string>
     */
    public array $specialities = [];

    /** In km to the nearest place in reach; null without a point, or for a member found remotely only. */
    public ?float $distance = null;

    /** Whether the member works remotely. */
    public bool $online = false;

    /** The cheapest offer per track, in cents, or the cheapest of any unit without one: « dès 80 € le titre ». */
    public ?int $price = null;

    /** The OfferUnit value of that price. */
    public ?string $priceUnit = null;

    public ?int $turnaroundDays = null;

    /** @var array<TeacherProfileStyle> */
    #[ApiProperty(genId: false)]
    public array $styles = [];

    /** The first media, which « Écouter » plays without leaving the list. */
    #[ApiProperty(genId: false)]
    public ?TeacherSearchMedia $media = null;
}
