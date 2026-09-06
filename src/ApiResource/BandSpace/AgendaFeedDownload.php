<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\AgendaFeedDownloadProvider;

/**
 * The public feed itself, fetched by the subscriber's calendar client.
 *
 * No `security:` attribute on purpose: a calendar client sends no cookie and no JWT. The token in
 * the path is the whole credential, and `^/api/shares/` is already PUBLIC_ACCESS in security.yaml,
 * which is the same door the file share links use.
 */
#[ApiResource(
    shortName: 'AgendaFeedDownload',
    operations: [
        new Get(
            uriTemplate: '/shares/agenda/{token}.ics',
            openapi: new Operation(tags: ['Band Space Agenda']),
            output: false,
            name: 'api_band_space_agenda_feed_download',
            provider: AgendaFeedDownloadProvider::class,
        ),
    ],
)]
class AgendaFeedDownload
{
    #[ApiProperty(identifier: true)]
    public string $token;
}
