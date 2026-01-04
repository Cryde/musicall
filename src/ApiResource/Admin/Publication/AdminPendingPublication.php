<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Publication;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Publication;
use App\State\Provider\Admin\Publication\AdminPendingPublicationProvider;

#[GetCollection(
    uriTemplate: '/admin/publications/pending',
    openapi: new Operation(tags: ['Admin Publications']),
    shortName: 'Publication',
    paginationEnabled: false,
    normalizationContext: ['groups' => [Publication::LIST], 'skip_null_values' => false],
    security: 'is_granted("ROLE_ADMIN")',
    name: 'api_admin_publications_pending_list',
    provider: AdminPendingPublicationProvider::class,
)]
class AdminPendingPublication
{
}
