<?php declare(strict_types=1);

namespace App\ApiResource\Admin\Report;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Admin\Report\AdminReportSuspendAuthorProcessor;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/** « Suspendre le compte » of the reported content's author, closing the reports on that content. */
#[Post(
    uriTemplate: '/admin/reports/{id}/suspend-author',
    status: Response::HTTP_NO_CONTENT,
    openapi: new Operation(tags: ['Admin Reports']),
    security: 'is_granted("ROLE_ADMIN")',
    output: false,
    read: false,
    name: 'api_admin_reports_suspend_author',
    processor: AdminReportSuspendAuthorProcessor::class,
)]
class AdminReportSuspendAuthor
{
    #[Assert\NotBlank(message: 'Veuillez indiquer la raison de la suspension')]
    #[Assert\Length(max: 500, maxMessage: 'La raison ne peut pas dépasser {{ limit }} caractères')]
    public string $reason = '';
}
