<?php declare(strict_types=1);

namespace App\ApiResource\Admin\Feedback;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Enum\Feedback\FeedbackStatus;
use App\State\Processor\Admin\Feedback\AdminFeedbackStatusUpdateProcessor;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Moving one report through triage.
 *
 * A Post rather than a Patch because nothing under src/ApiResource/Admin/ uses Patch: a status
 * change is modelled as its own single purpose resource returning 204.
 */
#[Post(
    uriTemplate: '/admin/feedbacks/{id}/status',
    status: Response::HTTP_NO_CONTENT,
    openapi: new Operation(tags: ['Admin Feedback']),
    security: 'is_granted("ROLE_ADMIN")',
    name: 'api_admin_feedbacks_status_update',
    processor: AdminFeedbackStatusUpdateProcessor::class,
)]
class AdminFeedbackStatusUpdate
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[Assert\NotBlank(message: 'Veuillez choisir un statut')]
    #[Assert\Choice(callback: [FeedbackStatus::class, 'values'], message: 'Statut inconnu')]
    public string $status;
}
