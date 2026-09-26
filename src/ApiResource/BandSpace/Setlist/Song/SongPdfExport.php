<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\Setlist\Song\SongPdfExportProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A song's lyrics as a PDF (#1055), with or without the chords and the singers, in any key.
 */
#[ApiResource(
    shortName: 'SongPdfExport',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs/{id}/pdf',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            security: "is_granted('ROLE_USER')",
            output: false,
            name: 'api_band_space_songs_pdf_export',
            provider: SongPdfExportProvider::class,
            parameters: [
                'chords' => new QueryParameter(key: 'chords', constraints: [new Assert\Choice(choices: self::BOOLEANS, message: 'Valeur invalide')]),
                'singers' => new QueryParameter(key: 'singers', constraints: [new Assert\Choice(choices: self::BOOLEANS, message: 'Valeur invalide')]),
                'transpose' => new QueryParameter(key: 'transpose', constraints: [
                    new Assert\Sequentially([
                        new Assert\Regex(pattern: '/^-?\d{1,2}\z/', message: 'La transposition doit être un nombre de demi-tons'),
                        new Assert\Range(min: -11, max: 11, notInRangeMessage: 'La transposition doit être entre {{ min }} et {{ max }} demi-tons'),
                    ]),
                ]),
            ],
        ),
    ],
)]
class SongPdfExport
{
    public const array BOOLEANS = ['0', '1', 'true', 'false'];

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $id;
}
