<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Search;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Teacher\Public\TeacherProfileStyle;
use App\Entity\Attribute\Instrument;
use App\Enum\Service\TeachingSearchOrder;
use App\Enum\Teacher\AgeGroup;
use App\Enum\Teacher\StudentLevel;
use App\State\ParameterProvider\UuidLinkParameterProvider;
use App\State\Provider\Service\TeacherSearchProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The teacher search (#1314), the first kind of the service search (#683): each later kind gets an operation of its
 * own, declaring its own filters over the shared query of ServiceRepository.
 */
#[GetCollection(
    uriTemplate: '/services/teaching',
    openapi: new Operation(tags: ['Service']),
    paginationEnabled: false,
    name: 'api_services_teaching_search',
    provider: TeacherSearchProvider::class,
    parameters: [
        'instrument' => new QueryParameter(
            key: 'instrument',
            provider: UuidLinkParameterProvider::class,
            description: 'The instrument taught (optional)',
            hydra: false,
            extraProperties: [
                'resource_class' => Instrument::class,
                'invalid_message' => 'search.instrument.invalid',
                'not_found_message' => 'search.instrument.not_found',
            ],
        ),
        'lat' => new QueryParameter(
            key: 'lat',
            schema: ['type' => 'number', 'minimum' => -90, 'maximum' => 90],
            description: 'The latitude of the searched point (optional, with lng)',
            constraints: [new Assert\Sequentially([
                new Assert\Regex(pattern: self::COORDINATE_PATTERN, message: 'search.coordinate.invalid'),
                new Assert\Range(min: -90, max: 90, notInRangeMessage: 'search.coordinate.invalid'),
            ])],
        ),
        'lng' => new QueryParameter(
            key: 'lng',
            schema: ['type' => 'number', 'minimum' => -180, 'maximum' => 180],
            description: 'The longitude of the searched point (optional, with lat)',
            constraints: [new Assert\Sequentially([
                new Assert\Regex(pattern: self::COORDINATE_PATTERN, message: 'search.coordinate.invalid'),
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
        'level' => new QueryParameter(
            key: 'level',
            schema: ['type' => 'string', 'enum' => [StudentLevel::BEGINNER->value, StudentLevel::INTERMEDIATE->value, StudentLevel::ADVANCED->value]],
            description: 'A level the teacher accepts (optional)',
            constraints: [new Assert\Choice(callback: [StudentLevel::class, 'values'], message: 'search.teacher.level.invalid')],
        ),
        'age_group' => new QueryParameter(
            key: 'age_group',
            schema: ['type' => 'string', 'enum' => [AgeGroup::CHILDREN->value, AgeGroup::TEENAGERS->value, AgeGroup::ADULTS->value, AgeGroup::SENIORS->value]],
            description: 'An age group the teacher accepts (optional)',
            constraints: [new Assert\Choice(callback: [AgeGroup::class, 'values'], message: 'search.teacher.age_group.invalid')],
        ),
        // Widens a search around a point: a teacher who teaches online counts wherever they are.
        'online' => new QueryParameter(
            key: 'online',
            schema: ['type' => 'string', 'enum' => ['0', '1']],
            description: 'Set to 1 to also find the teachers who teach online, wherever they are (optional)',
            hydra: false,
            constraints: [new Assert\Choice(choices: ['0', '1'], message: 'search.flag.invalid')],
        ),
        'free_trial' => new QueryParameter(
            key: 'free_trial',
            schema: ['type' => 'string', 'enum' => ['0', '1']],
            description: 'Set to 1 to keep only the teachers whose trial lesson is free (optional)',
            constraints: [new Assert\Choice(choices: ['0', '1'], message: 'search.flag.invalid')],
        ),
        'order' => new QueryParameter(
            key: 'order',
            schema: ['type' => 'string', 'enum' => ['distance', 'price', 'free_trial']],
            description: 'distance (needs lat and lng), price (a lesson per 30 minutes) or free_trial. By default the nearest first with a point, the newest otherwise',
            hydra: false,
            constraints: [new Assert\Choice(callback: [TeachingSearchOrder::class, 'values'], message: 'search.order.invalid')],
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
// Not served, only the address of each member. Declared so a username may hold a dot, as FeaturedTeacher does.
#[NotExposed(
    uriTemplate: '/teacher_search_results/{username}',
    requirements: ['username' => '[^/]+'],
)]
class TeacherSearchResult
{
    public const int PAGE_SIZE = 12;
    private const int MAX_PAGE = 1000;
    private const string COORDINATE_PATTERN = '/^-?\d{1,3}(\.\d+)?\z/';

    #[ApiProperty(identifier: true)]
    public string $username;

    /** What the site calls the teacher: their display name, or their username. */
    public string $name;

    public ?string $profilePictureUrl = null;

    /**
     * The searched instrument first.
     *
     * @var list<TeacherSearchInstrument>
     */
    #[ApiProperty(genId: false)]
    public array $instruments = [];

    /** In km to the nearest place in reach; null without a point, or for a teacher found online only. */
    public ?float $distance = null;

    /** Whether the teacher teaches online. */
    public bool $online = false;

    /** The cheapest lesson, in cents, with its length: « dès 25 € les 30 min ». Null without a lesson price. */
    public ?int $lessonPrice = null;

    public ?int $lessonMinutes = null;

    public bool $freeTrial = false;

    /** @var list<string> */
    public array $studentLevels = [];

    /** @var array<TeacherProfileStyle> */
    #[ApiProperty(genId: false)]
    public array $styles = [];

    /** The first media, which « Écouter » plays without leaving the list. */
    #[ApiProperty(genId: false)]
    public ?TeacherSearchMedia $media = null;
}
