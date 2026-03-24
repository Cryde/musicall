<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Finance;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Symfony\Component\Validator\Constraints\Date;
use App\State\Processor\BandSpace\FinanceEntryDeleteProcessor;
use App\State\Processor\BandSpace\FinanceEntryUpdateProcessor;
use App\State\Provider\BandSpace\FinanceEntryCollectionProvider;
use App\State\Provider\BandSpace\FinanceEntryItemProvider;

#[ApiResource(
    shortName: 'FinanceEntry',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/entries',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_entries_get_collection',
            provider: FinanceEntryCollectionProvider::class,
            parameters: [
                'from' => new QueryParameter(key: 'from', constraints: [new Date()]),
                'to' => new QueryParameter(key: 'to', constraints: [new Date()]),
            ],
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/entries/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_entries_get_item',
            provider: FinanceEntryItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/entries/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_entries_patch',
            provider: FinanceEntryItemProvider::class,
            processor: FinanceEntryUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/entries/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_entries_delete',
            provider: FinanceEntryItemProvider::class,
            processor: FinanceEntryDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class FinanceEntryResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public string $categoryId;
    public string $categoryName;
    public string $label;
    public string $type;
    public string $status;
    public ?int $amount = null;
    public ?int $amountMin = null;
    public ?int $amountMax = null;
    public string $date;
    public string $scope;
    public ?string $memberId = null;
    public ?string $memberName = null;
    public bool $isFormerMember = false;
    public ?string $recurrenceId = null;
    public bool $splitWarning = false;
    public string $creationDatetime;
    public ?string $updateDatetime = null;
}
