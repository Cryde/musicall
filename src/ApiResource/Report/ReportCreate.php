<?php declare(strict_types=1);

namespace App\ApiResource\Report;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Report\Report;
use App\Enum\Report\ReportReason;
use App\Enum\Report\ReportTargetType;
use App\State\Processor\Report\ReportCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Reporting content or a user to the moderators (#1116). Always 204 with no body: the reporter learns
 * nothing about the outcome, and a repeat while their report is pending changes nothing.
 */
#[Post(
    uriTemplate: '/reports',
    status: 204,
    openapi: new Operation(tags: ['Reports']),
    security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
    output: false,
    name: 'api_reports_post',
    processor: ReportCreateProcessor::class,
)]
class ReportCreate
{
    #[Assert\NotBlank(message: 'report.target.required')]
    #[Assert\Choice(callback: [ReportTargetType::class, 'values'], message: 'report.target_type.unknown')]
    public string $targetType = '';

    #[Assert\NotBlank(message: 'report.target.required')]
    #[Assert\Length(max: 36, maxMessage: 'common.invalid_identifier')]
    public string $targetId = '';

    #[Assert\NotBlank(message: 'report.reason.required')]
    #[Assert\Choice(callback: [ReportReason::class, 'values'], message: 'report.reason.unknown')]
    public string $reason = '';

    #[Assert\Length(max: Report::DETAILS_MAX_LENGTH, maxMessage: 'report.details.too_long')]
    public ?string $details = null {
        set(?string $value) {
            $value = $value === null ? null : trim($value);
            $this->details = $value === '' ? null : $value;
        }
    }
}
