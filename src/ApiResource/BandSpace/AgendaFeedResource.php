<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\AgendaFeedGenerateProcessor;
use App\State\Processor\BandSpace\AgendaFeedRevokeProcessor;
use App\State\Provider\BandSpace\AgendaFeedProvider;

/**
 * A member's own iCal subscription for one band space: whether one exists, and how it is doing.
 *
 * Member level rather than admin level, and singular rather than a collection: the feed belongs to
 * the person, and each membership holds at most one. Nobody, admin included, reads anyone else's.
 */
#[ApiResource(
    shortName: 'AgendaFeed',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/agenda-feed',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_agenda_feed_get',
            provider: AgendaFeedProvider::class,
        ),
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/agenda-feed',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            security: "is_granted('ROLE_USER')",
            input: false,
            output: AgendaFeedGenerated::class,
            name: 'api_band_space_agenda_feed_post',
            processor: AgendaFeedGenerateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/agenda-feed',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_agenda_feed_delete',
            // API Platform reads before it writes on a Delete, and with no provider the default one
            // finds nothing and answers 404 before the processor is ever reached.
            provider: AgendaFeedProvider::class,
            processor: AgendaFeedRevokeProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class AgendaFeedResource
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public bool $isEnabled = false;

    /**
     * Never the URL: only the token's sha256 is stored, so the feed cannot be shown again after the
     * response that created it.
     */
    public ?\DateTimeInterface $creationDatetime = null;

    public ?\DateTimeInterface $lastAccessDatetime = null;

    public int $accessCount = 0;
}
