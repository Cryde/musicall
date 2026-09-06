<?php declare(strict_types=1);

namespace App\ApiResource\Admin\User;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Admin\User\AdminUserCollectionProvider;
use App\State\Provider\Admin\User\AdminUserItemProvider;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Looking one account up, and seeing enough about it to make a decision.
 *
 * Email is searchable on purpose: it is often the only thing you have when somebody writes in about
 * their own account. That is also why the collection refuses to answer without a search term, so it
 * cannot become a way to page through the whole user base.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/users',
            openapi: new Operation(tags: ['Admin Users']),
            paginationEnabled: true,
            paginationItemsPerPage: self::ITEMS_PER_PAGE,
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_admin_users_search',
            provider: AdminUserCollectionProvider::class,
            parameters: [
                'search' => new QueryParameter(
                    key: 'search',
                    constraints: [new Assert\Length(min: self::SEARCH_MIN_LENGTH, minMessage: 'Saisissez au moins {{ limit }} caractères')],
                ),
            ],
        ),
        new Get(
            uriTemplate: '/admin/users/{id}',
            openapi: new Operation(tags: ['Admin Users']),
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_admin_users_get',
            provider: AdminUserItemProvider::class,
        ),
    ],
)]
class AdminUser
{
    /** Both mirrored by assets/js/constants/adminUser.js, pinned by AdminUserRolesClientMirrorTest. */
    final public const int SEARCH_MIN_LENGTH = 2;
    final public const int ITEMS_PER_PAGE = 20;

    #[ApiProperty(identifier: true)]
    public string $id;

    public string $username;
    public string $email;

    /**
     * The roles as stored, not as resolved. User::getRoles() adds ROLE_USER to everyone and the
     * hierarchy is applied server side, so showing the resolved set would show a row nobody wrote
     * and that the roles screen cannot turn off.
     *
     * @var list<string>
     */
    public array $roles = [];

    public DateTimeInterface $creationDatetime;
    public ?DateTimeInterface $lastLoginDatetime = null;
    public ?DateTimeInterface $lastActivityDatetime = null;
    public ?DateTimeInterface $confirmationDatetime = null;
    public ?DateTimeInterface $usernameChangedDatetime = null;

    /** Soft delete: the account is anonymised rather than removed, so the row is still here. */
    public ?DateTimeInterface $deletionDatetime = null;

    public bool $isDeleted = false;
    public bool $isEmailConfirmed = false;
    public bool $hasMusicianProfile = false;
    public bool $hasTeacherProfile = false;
    public ?string $profilePictureUrl = null;

    /**
     * Null on the collection, which answers who rather than what.
     *
     * genId: false because this is an embedded value rather than a resource of its own. Without it
     * API Platform mints a random .well-known/genid IRI for it on every request, which is both
     * meaningless and unassertable.
     */
    #[ApiProperty(genId: false)]
    public ?AdminUserActivity $activity = null;
}
