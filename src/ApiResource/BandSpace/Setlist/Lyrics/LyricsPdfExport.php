<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\Service\BandSpace\Song\SongSheetOptions;
use App\State\Provider\BandSpace\Setlist\Lyrics\LyricsPdfExportProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Lyrics as a PDF (#1262), linked to a song or not, with the same options as the song PDF.
 */
#[ApiResource(
    shortName: 'LyricsPdfExport',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/lyrics/{id}/pdf',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            security: "is_granted('ROLE_USER')",
            output: false,
            name: 'api_band_space_lyrics_pdf_export',
            provider: LyricsPdfExportProvider::class,
            parameters: [
                'chords' => new QueryParameter(key: 'chords', constraints: [new Assert\Choice(choices: SongSheetOptions::FLAG_VALUES, message: 'band_space.song.pdf_option_invalid')]),
                'singers' => new QueryParameter(key: 'singers', constraints: [new Assert\Choice(choices: SongSheetOptions::FLAG_VALUES, message: 'band_space.song.pdf_option_invalid')]),
                'transpose' => new QueryParameter(key: 'transpose', constraints: [
                    new Assert\Sequentially([
                        new Assert\Regex(pattern: '/^-?\d{1,2}\z/', message: 'band_space.song.transpose_not_a_number'),
                        new Assert\Range(min: -11, max: 11, notInRangeMessage: 'band_space.song.transpose_out_of_range'),
                    ]),
                ]),
            ],
        ),
    ],
)]
class LyricsPdfExport
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $id;
}
