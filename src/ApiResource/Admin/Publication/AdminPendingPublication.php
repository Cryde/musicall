<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Publication;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Publication\PublicationListItem;
use App\State\Provider\Admin\Publication\AdminPendingPublicationProvider;

#[GetCollection(
    uriTemplate: '/admin/publications/pending',
    openapi: new Operation(tags: ['Admin Publications']),
    shortName: 'Publication',
    paginationEnabled: false,
    normalizationContext: ['skip_null_values' => false],
    security: 'is_granted("ROLE_ADMIN")',
    output: PublicationListItem::class,
    name: 'api_admin_publications_pending_list',
    provider: AdminPendingPublicationProvider::class,
)]
class AdminPendingPublication
{
}
