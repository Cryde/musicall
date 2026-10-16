<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\State\Processor\BandSpace\BandSpaceOpenedItemRecordProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * « The caller opened this item », for the palette's « Ouverts par moi » order (#1047). The client
 * sends it without waiting, so a failure never stands between a member and what they opened.
 */
#[ApiResource(
    shortName: 'BandSpaceOpenedItem',
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/opened_items',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            status: 204,
            openapi: new Operation(tags: ['Band Space Search']),
            security: "is_granted('ROLE_USER')",
            output: false,
            read: false,
            name: 'api_band_space_opened_items_post',
            processor: BandSpaceOpenedItemRecordProcessor::class,
        ),
    ],
)]
class BandSpaceOpenedItemRecord
{
    /** From the URI, never the body: the processor reads the URI variable. */
    #[ApiProperty(identifier: true, writable: false)]
    public ?string $bandSpaceId = null;

    /** A BandSpaceSearchResultType value: the kinds the palette lists. */
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'common.type.required'),
        new Assert\Choice(callback: [BandSpaceSearchResultType::class, 'values'], message: 'Type de résultat inconnu'),
    ])]
    public ?string $type = null;

    /** The record's own id, the palette result's `resource_id`. */
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Veuillez spécifier un identifiant'),
        new Assert\Uuid(message: 'L\'identifiant est invalide'),
    ])]
    public ?string $resourceId = null;
}
