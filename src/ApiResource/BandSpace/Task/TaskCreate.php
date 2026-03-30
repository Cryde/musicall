<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Task;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TaskCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/tasks',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: TaskResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Task']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    output: TaskResource::class,
    name: 'api_band_space_tasks_post',
    processor: TaskCreateProcessor::class,
)]
class TaskCreate
{
    #[Assert\NotBlank(message: 'Veuillez spécifier un titre')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
    public string $title;

    public ?string $description = null;

    #[Assert\Choice(choices: ['todo', 'in_progress', 'done'], message: 'Le statut doit être "todo", "in_progress" ou "done"')]
    public string $status = 'todo';

    #[Assert\Choice(choices: ['normal', 'high', 'urgent'], message: 'La priorité doit être "normal", "high" ou "urgent"')]
    public string $priority = 'normal';

    public ?string $dueDate = null;

    #[Assert\Uuid(message: 'Identifiant de catégorie invalide')]
    public ?string $categoryId = null;

    /**
     * @var string[]|null
     */
    #[Assert\All([
        new Assert\Uuid(message: 'Identifiant d\'assigné invalide'),
    ])]
    public ?array $assigneeIds = null;
}
