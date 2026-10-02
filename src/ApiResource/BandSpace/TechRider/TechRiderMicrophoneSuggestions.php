<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\TechRider;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\TechRider\TechRiderMicrophoneSuggestionsProvider;

/**
 * What the patch list offers in its Micro / DI cells (#1099): what this band already uses, then the
 * catalogue. One object rather than a collection, since a suggestion has no address of its own.
 */
#[Get(
    uriTemplate: '/band_spaces/{bandSpaceId}/tech_rider_microphones',
    openapi: new Operation(tags: ['Band Space Tech Rider']),
    security: "is_granted('ROLE_USER')",
    name: 'api_band_space_tech_rider_microphones',
    provider: TechRiderMicrophoneSuggestionsProvider::class,
)]
class TechRiderMicrophoneSuggestions
{
    /** @var list<array{name: string, usage_count: int}> */
    #[ApiProperty(genId: false)]
    public array $used = [];

    /** @var list<string> the catalogue, less what is already in `used` */
    public array $catalogue = [];
}
