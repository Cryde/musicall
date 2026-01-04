<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Publication;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Admin\Publication\AdminPublicationApproveProcessor;
use App\State\Provider\Admin\Publication\AdminPublicationActionProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/publications/{id}/approve',
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(tags: ['Admin Publications']),
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_admin_publications_approve',
            provider: AdminPublicationActionProvider::class,
            processor: AdminPublicationApproveProcessor::class,
        ),
    ]
)]
class AdminPublicationApprove
{
    #[ApiProperty(identifier: true)]
    public int $id;
}
