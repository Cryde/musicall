<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Forum\ForumPostReport;
use App\State\Processor\Forum\ForumPostReportProcessor;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/forums/posts/{id}/report',
    uriVariables: ['id'],
    status: Response::HTTP_NO_CONTENT,
    openapi: new Operation(tags: ['Forum']),
    security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
    name: 'api_forum_post_report',
    processor: ForumPostReportProcessor::class,
)]
class ForumPostReportInput
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[Assert\NotBlank(message: 'Veuillez préciser le motif du signalement', normalizer: 'trim')]
    #[Assert\Length(
        max: ForumPostReport::REASON_MAX_LENGTH,
        maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères',
        normalizer: 'trim',
    )]
    public string $reason;
}
