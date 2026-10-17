<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Finance;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\FinanceCategoryCreateProcessor;
use App\Validator\BandSpace\CategoryMaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/finance/categories',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: FinanceCategoryResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Finance']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    output: FinanceCategoryResource::class,
    name: 'api_band_space_finance_categories_post',
    processor: FinanceCategoryCreateProcessor::class,
)]
#[CategoryMaxDepth]
class FinanceCategoryCreate
{
    #[Assert\NotBlank(message: 'common.name.required')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'common.name.too_long'
    )]
    public string $name;

    #[Assert\Uuid(message: 'band_space.finance.category.parent_invalid_identifier')]
    public ?string $parentId = null;
}
