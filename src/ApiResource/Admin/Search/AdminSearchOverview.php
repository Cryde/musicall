<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Search;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Admin\Search\AdminSearchOverviewProvider;
use Symfony\Component\Validator\Constraints as Assert;

/** The musician searches over a period, for the admin (#1075). */
#[Get(
    uriTemplate: '/admin/searches/overview',
    openapi: new Operation(tags: ['Admin Search']),
    security: 'is_granted("ROLE_ADMIN")',
    name: 'api_admin_searches_overview',
    provider: AdminSearchOverviewProvider::class,
    parameters: [
        'from' => new QueryParameter(key: 'from', constraints: [new Assert\Sequentially([new Assert\NotBlank(), new Assert\Date()])]),
        'to' => new QueryParameter(key: 'to', constraints: [new Assert\Sequentially([new Assert\NotBlank(), new Assert\Date()])]),
    ],
)]
class AdminSearchOverview
{
    public string $from;
    public string $to;
    public int $searches = 0;
    public int $aiSearches = 0;
    public int $zeroResultSearches = 0;
    public int $aiFilters = 0;
    public int $aiNothing = 0;
    public int $aiFailed = 0;

    /** @var list<AdminSearchCombination> */
    #[ApiProperty(genId: false)]
    public array $topCombinations = [];
}
