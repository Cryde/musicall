<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Finance;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\FinanceRecurrenceDeleteProcessor;
use App\State\Processor\BandSpace\FinanceRecurrenceUpdateProcessor;
use App\State\Provider\BandSpace\FinanceRecurrenceCollectionProvider;
use App\State\Provider\BandSpace\FinanceRecurrenceItemProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'FinanceRecurrence',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/recurrences',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_recurrences_get_collection',
            provider: FinanceRecurrenceCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/recurrences/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_recurrences_get_item',
            provider: FinanceRecurrenceItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/recurrences/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_recurrences_patch',
            provider: FinanceRecurrenceItemProvider::class,
            processor: FinanceRecurrenceUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/recurrences/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_recurrences_delete',
            provider: FinanceRecurrenceItemProvider::class,
            processor: FinanceRecurrenceDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class FinanceRecurrenceResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public string $categoryId;
    public string $categoryName;

    #[Assert\NotBlank(message: 'Veuillez spécifier un libellé')]
    #[Assert\Length(max: 255, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères')]
    public string $label;

    public string $type;
    public int $amount;
    public string $scope;
    public string $interval;
    public string $startDate;
    public string $endDate;
    public bool $isActive;
    public int $entryCount = 0;
    public string $creationDatetime;
    public ?string $updateDatetime = null;
}
