<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Search;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Admin\Search\AdminAiSearchCollectionProvider;
use DateTimeInterface;

/** A search typed in words, and what the AI made of it (#1075). */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/searches/ai',
            openapi: new Operation(tags: ['Admin Search']),
            paginationEnabled: true,
            paginationItemsPerPage: 25,
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_admin_searches_ai_list',
            provider: AdminAiSearchCollectionProvider::class,
        ),
        // Not served: here so each row's @id names an address under the admin rather than an invented one.
        new NotExposed(uriTemplate: '/admin/searches/ai/{id}'),
    ],
)]
class AdminAiSearch
{
    #[ApiProperty(identifier: true)]
    public string $id;

    public string $query;
    /** filters, nothing or failed */
    public string $outcome;
    public ?int $type = null;
    public ?string $instrumentName = null;
    /** @var list<string> */
    public array $styleNames = [];
    public ?float $latitude = null;
    public ?float $longitude = null;
    public bool $authenticated = false;
    public DateTimeInterface $searchDatetime;
}
