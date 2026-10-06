<?php declare(strict_types=1);

namespace App\ApiResource\Admin\User;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Admin\User\AdminUserSuspensionLiftProcessor;
use Symfony\Component\HttpFoundation\Response;

/** Lifts a suspension (#1116). */
#[Post(
    uriTemplate: '/admin/users/{id}/lift-suspension',
    status: Response::HTTP_NO_CONTENT,
    openapi: new Operation(tags: ['Admin Users']),
    security: 'is_granted("ROLE_ADMIN")',
    input: false,
    output: false,
    read: false,
    name: 'api_admin_users_lift_suspension',
    processor: AdminUserSuspensionLiftProcessor::class,
)]
class AdminUserSuspensionLift
{
}
