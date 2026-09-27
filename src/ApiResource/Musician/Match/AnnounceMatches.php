<?php

declare(strict_types=1);

namespace App\ApiResource\Musician\Match;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Musician\Match\AnnounceMatchesProvider;

/**
 * « Annonces pour vous » (#1082): the announces answering the member's latest ones, best first. One
 * object rather than a collection, since a match is not a resource of its own.
 */
#[Get(
    uriTemplate: '/user/musician/announces/matches',
    openapi: new Operation(tags: ['Musician announce']),
    security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
    name: 'api_user_musician_announce_matches',
    provider: AnnounceMatchesProvider::class,
)]
class AnnounceMatches
{
    /** @var list<AnnounceMatchItem> */
    #[ApiProperty(genId: false)]
    public array $matches = [];
}
