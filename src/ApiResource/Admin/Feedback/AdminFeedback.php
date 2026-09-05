<?php declare(strict_types=1);

namespace App\ApiResource\Admin\Feedback;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\Enum\Feedback\FeedbackModule;
use App\Enum\Feedback\FeedbackStatus;
use App\Enum\Feedback\FeedbackType;
use App\State\Provider\Admin\Feedback\AdminFeedbackCollectionProvider;
use App\State\Provider\Admin\Feedback\AdminFeedbackItemProvider;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The triage list.
 *
 * Paginated in the database, unlike every other admin collection, which is `paginationEnabled: false`
 * with a client side DataTable. Those list a queue that moderation drains; this one is the only admin
 * table nothing prunes, so it only grows.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/feedbacks',
            openapi: new Operation(tags: ['Admin Feedback']),
            paginationEnabled: true,
            paginationItemsPerPage: 25,
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_admin_feedbacks_list',
            provider: AdminFeedbackCollectionProvider::class,
            // Declared rather than parsed in the provider, so an unknown value is a 422 naming the
            // parameter before the provider runs and the filters can be trusted. `values()` cannot go
            // in a `schema` here: an attribute argument has to be a constant expression, and a static
            // call is not one.
            parameters: [
                'status' => new QueryParameter(key: 'status', constraints: [new Assert\Choice(callback: [FeedbackStatus::class, 'values'], message: 'Statut inconnu')]),
                'module' => new QueryParameter(key: 'module', constraints: [new Assert\Choice(callback: [FeedbackModule::class, 'values'], message: 'Section inconnue')]),
                'type' => new QueryParameter(key: 'type', constraints: [new Assert\Choice(callback: [FeedbackType::class, 'values'], message: 'Type de retour inconnu')]),
            ],
        ),
        // Exists so the item IRI the collection advertises resolves. Without it API Platform still
        // emits an @id per row, built from the short name, pointing at a route that does not exist.
        new Get(
            uriTemplate: '/admin/feedbacks/{id}',
            openapi: new Operation(tags: ['Admin Feedback']),
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_admin_feedbacks_get',
            provider: AdminFeedbackItemProvider::class,
        ),
    ],
)]
class AdminFeedback
{
    #[ApiProperty(identifier: true)]
    public string $id;

    public string $type;
    public string $typeLabel;
    public string $module;
    public string $moduleLabel;
    public string $message;
    public ?string $email = null;
    public ?string $username = null;
    public ?string $bandSpaceName = null;
    public string $pageUrl;
    public ?string $userAgent = null;
    public string $status;
    public string $statusLabel;
    public DateTimeInterface $creationDatetime;
}
