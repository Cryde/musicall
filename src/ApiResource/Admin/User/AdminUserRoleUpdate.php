<?php declare(strict_types=1);

namespace App\ApiResource\Admin\User;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Admin\User\AdminUserRoleUpdateProcessor;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Setting which of the grantable roles an account holds.
 *
 * A Post rather than a Patch, like every other admin write: nothing under src/ApiResource/Admin/
 * uses Patch. The whole grantable set is sent rather than one toggle at a time, so the request says
 * what the roles should be and repeating it changes nothing.
 *
 * This is the one screen that hands out privilege, so it is deliberately narrow:
 *
 *  - only the two roles below can be written, anything else is a 422, and a role the account holds
 *    that is not in this list is preserved untouched rather than silently dropped
 *  - an admin cannot change their own roles, enforced in the processor. That stops somebody
 *    removing their own ROLE_ADMIN and locking themselves out of the back office, and it removes
 *    the simplest self escalation path
 *  - ROLE_USER is absent because User::getRoles() gives it to everyone already
 */
#[Post(
    uriTemplate: '/admin/users/{id}/roles',
    status: Response::HTTP_NO_CONTENT,
    openapi: new Operation(tags: ['Admin Users']),
    security: 'is_granted("ROLE_ADMIN")',
    name: 'api_admin_users_roles_update',
    processor: AdminUserRoleUpdateProcessor::class,
)]
class AdminUserRoleUpdate
{
    /**
     * ROLE_TESTER reveals modules that are merged but not yet announced and grants no permission of
     * its own, so it is the low stakes one. ROLE_ADMIN is the one worth a confirmation in the UI.
     *
     * @var list<string>
     */
    final public const array GRANTABLE_ROLES = ['ROLE_ADMIN', 'ROLE_TESTER'];

    #[ApiProperty(identifier: true)]
    public string $id;

    /**
     * @var list<string>
     */
    // Count first: Assert\Unique scans the array for each element, so without a bound a payload of
    // thousands of junk strings burns CPU quadratically before anything rejects it. Only an
    // authenticated admin can reach this, so it is self inflicted rather than a way in, but the
    // whole list can only ever hold two values and saying so costs one line.
    #[Assert\Count(max: 2, maxMessage: 'Trop de rôles envoyés')]
    #[Assert\Unique(message: 'Un rôle est répété')]
    #[Assert\All([
        new Assert\Choice(choices: self::GRANTABLE_ROLES, message: 'Rôle inconnu ou non attribuable'),
    ])]
    public array $roles = [];
}
