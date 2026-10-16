<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Task;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TaskCategoryCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/task-categories',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: TaskCategoryResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Task']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    output: TaskCategoryResource::class,
    name: 'api_band_space_task_categories_post',
    processor: TaskCategoryCreateProcessor::class,
)]
class TaskCategoryCreate
{
    #[Assert\NotBlank(message: 'common.name.required')]
    #[Assert\Length(max: 255, maxMessage: 'common.name.too_long')]
    public string $name;
}
