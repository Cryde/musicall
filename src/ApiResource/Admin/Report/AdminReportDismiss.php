<?php declare(strict_types=1);

namespace App\ApiResource\Admin\Report;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Admin\Report\AdminReportDismissProcessor;
use Symfony\Component\HttpFoundation\Response;

/** « Classer sans suite »: closes every pending report on the same content. */
#[Post(
    uriTemplate: '/admin/reports/{id}/dismiss',
    status: Response::HTTP_NO_CONTENT,
    openapi: new Operation(tags: ['Admin Reports']),
    security: 'is_granted("ROLE_ADMIN")',
    input: false,
    output: false,
    read: false,
    name: 'api_admin_reports_dismiss',
    processor: AdminReportDismissProcessor::class,
)]
class AdminReportDismiss
{
}
