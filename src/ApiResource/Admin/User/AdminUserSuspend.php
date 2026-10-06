<?php declare(strict_types=1);

namespace App\ApiResource\Admin\User;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Admin\User\AdminUserSuspendProcessor;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/** Suspends an account from its admin page (#1116). */
#[Post(
    uriTemplate: '/admin/users/{id}/suspend',
    status: Response::HTTP_NO_CONTENT,
    openapi: new Operation(tags: ['Admin Users']),
    security: 'is_granted("ROLE_ADMIN")',
    output: false,
    read: false,
    name: 'api_admin_users_suspend',
    processor: AdminUserSuspendProcessor::class,
)]
class AdminUserSuspend
{
    #[Assert\NotBlank(message: 'Veuillez indiquer la raison de la suspension')]
    #[Assert\Length(max: 500, maxMessage: 'La raison ne peut pas dépasser {{ limit }} caractères')]
    public string $reason = '';
}
