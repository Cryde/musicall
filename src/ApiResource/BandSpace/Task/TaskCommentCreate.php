<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Task;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TaskCommentCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/tasks/{taskId}/comments',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: TaskCommentResource::class, identifiers: ['bandSpaceId']),
        'taskId' => new Link(fromClass: TaskCommentResource::class, identifiers: ['taskId']),
    ],
    openapi: new Operation(tags: ['Band Space Task']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    output: TaskCommentResource::class,
    name: 'api_band_space_task_comments_post',
    processor: TaskCommentCreateProcessor::class,
)]
class TaskCommentCreate
{
    #[Assert\NotBlank(message: 'Veuillez saisir un commentaire')]
    #[Assert\Length(max: 5000, maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères')]
    public string $content;
}
