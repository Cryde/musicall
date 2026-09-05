<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\BandSpaceActivityCollectionProvider;
use App\State\Provider\BandSpace\BandSpaceActivityItemProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'BandSpaceActivity',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/activities',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Activity']),
            paginationEnabled: true,
            paginationItemsPerPage: 50,
            paginationMaximumItemsPerPage: 200,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_activities_get_collection',
            provider: BandSpaceActivityCollectionProvider::class,
            parameters: [
                // Instants, not calendar days, and the only date parameters in the app that are.
                // The picker is day granular but the client turns the day into an instant itself,
                // because only the client knows its own offset: a viewer in Paris asking for the 5th
                // means 22:00 UTC on the 4th, and a server reading `2026-09-05` as a UTC day would
                // silently clip the first two hours they meant to see. So Assert\Date is the wrong
                // constraint here; ATOM is the shape, and Assert\DateTime only defaults to
                // `Y-m-d H:i:s`, it does not insist on it.
                'from' => new QueryParameter(key: 'from', constraints: [
                    new Assert\Sequentially([
                        new Assert\Regex(pattern: self::ATOM_PATTERN, message: self::ATOM_MESSAGE),
                        new Assert\DateTime(format: \DateTimeInterface::ATOM),
                    ]),
                ]),
                'to' => new QueryParameter(key: 'to', constraints: [
                    new Assert\Sequentially([
                        new Assert\Regex(pattern: self::ATOM_PATTERN, message: self::ATOM_MESSAGE),
                        new Assert\DateTime(format: \DateTimeInterface::ATOM),
                    ]),
                ]),
            ],
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/activities/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Activity']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_activities_get_item',
            provider: BandSpaceActivityItemProvider::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpaceActivityResource
{
    /**
     * ISO 8601 with an offset: `2026-09-05T00:00:00Z` or `...+02:00`, which is what the front end
     * sends once it has stripped the milliseconds `toISOString()` adds.
     *
     * The shape is checked before the calendar, and Sequentially makes that order load bearing.
     * `Assert\DateTime` is the only part that knows `2026-13-45T99:99:99Z` is not a real instant,
     * but it works by calling `DateTimeImmutable::createFromFormat()`, which raises a **ValueError**
     * on a null byte. A ValueError is not an Exception, so it escapes both Symfony's validator and
     * ParameterValidatorProvider, and `?from=%00` was a pre-auth 500: the same #934 class that
     * CalendarDay::parse avoids by using the constructor. This anchored pattern cannot match a
     * string carrying a null byte, so nothing dangerous reaches DateTimeValidator.
     *
     * `\z` rather than `$`, because `$` also matches before a trailing newline.
     */
    private const string ATOM_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})\z/';

    /** The wording Assert\DateTime uses, so which of the two rules fired is not a caller's problem. */
    private const string ATOM_MESSAGE = 'Cette valeur n\'est pas une date/heure valide.';

    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public string $module;
    public ?string $resourceId = null;
    public string $type;

    /** @var array<string, mixed>|null */
    public ?array $payload = null;

    /** @var array{id: string, username: string, profile_picture_url: ?string}|null */
    public ?array $actor = null;

    public \DateTimeInterface $creationDatetime;
}
