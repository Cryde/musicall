<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Admin\Forum\AdminForumReportResolveProcessor;
use App\State\Provider\Admin\Forum\AdminForumReportCollectionProvider;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    shortName: 'AdminForumReport',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/forum/reports',
            openapi: new Operation(tags: ['Admin Forum']),
            paginationEnabled: false,
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_admin_forum_reports_list',
            provider: AdminForumReportCollectionProvider::class,
        ),
        new Post(
            uriTemplate: '/admin/forum/reports/{id}/resolve',
            uriVariables: ['id'],
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(tags: ['Admin Forum']),
            security: 'is_granted("ROLE_ADMIN")',
            read: false,
            deserialize: false,
            validate: false,
            name: 'api_admin_forum_reports_resolve',
            processor: AdminForumReportResolveProcessor::class,
        ),
    ]
)]
class AdminForumReport
{
    #[ApiProperty(identifier: true)]
    public string $id;

    public string $reason;
    public DateTimeInterface $creationDatetime;
    public string $reporterUsername;

    public string $postId;
    public string $postExcerpt;
    public string $postAuthorUsername;

    public string $topicSlug;
    public string $topicTitle;

    /** Page the reported post sits on, so the admin can deep-link straight to it. */
    public int $topicPage;
}
