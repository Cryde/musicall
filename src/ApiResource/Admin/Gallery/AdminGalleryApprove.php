<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Gallery;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Admin\Gallery\AdminGalleryApproveProcessor;
use App\State\Provider\Admin\Gallery\AdminGalleryActionProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/galleries/{id}/approve',
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(tags: ['Admin Galleries']),
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_admin_galleries_approve',
            provider: AdminGalleryActionProvider::class,
            processor: AdminGalleryApproveProcessor::class,
        ),
    ]
)]
class AdminGalleryApprove
{
    #[ApiProperty(identifier: true)]
    public int $id;
}
