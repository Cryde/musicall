<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Gallery;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Publication\GalleryResource;
use App\State\Provider\Admin\Gallery\AdminPendingGalleryProvider;

#[GetCollection(
    uriTemplate: '/admin/galleries/pending',
    openapi: new Operation(tags: ['Admin Galleries']),
    shortName: 'Gallery',
    paginationEnabled: false,
    normalizationContext: ['groups' => [GalleryResource::LIST], 'skip_null_values' => false],
    security: 'is_granted("ROLE_ADMIN")',
    name: 'api_admin_galleries_pending_list',
    provider: AdminPendingGalleryProvider::class,
)]
class AdminPendingGallery
{
}
