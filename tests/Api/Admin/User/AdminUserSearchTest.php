<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\User;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class AdminUserSearchTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $this->client->request('GET', '/api/admin/users?search=someone');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_base_user_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/users?search=someone');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Access Denied. The user doesn't have ROLE_ADMIN.",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Access Denied. The user doesn't have ROLE_ADMIN.",
        ]);
    }

    public function test_admin_finds_by_username(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $target = UserFactory::new()->create([
            'username' => 'jean_michel',
            'email' => 'jm@email.com',
            'roles' => [],
            'creationDatetime' => new \DateTime('2026-01-15 09:00:00'),
            'lastLoginDatetime' => null,
        ]);
        UserFactory::new()->create(['username' => 'autre_personne', 'email' => 'autre@email.com']);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/users?search=jean');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminUser',
            '@id' => '/api/admin/users',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/admin/users/' . $target->id,
                    '@type' => 'AdminUser',
                    'id' => (string) $target->id,
                    'username' => 'jean_michel',
                    'email' => 'jm@email.com',
                    'roles' => [],
                    'creation_datetime' => '2026-01-15T09:00:00+00:00',
                    'is_deleted' => false,
                    'is_email_confirmed' => false,
                    'has_musician_profile' => false,
                    'has_teacher_profile' => false,
                ],
            ],
            'view' => ['@id' => '/api/admin/users?search=jean', '@type' => 'PartialCollectionView'],
        ], 'The search list carries no activity block: it answers who, not what');
    }

    public function test_admin_finds_by_email(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $target = UserFactory::new()->create([
            'username' => 'jean_michel',
            'email' => 'contact.utile@email.com',
            'roles' => [],
            'creationDatetime' => new \DateTime('2026-01-15 09:00:00'),
            'lastLoginDatetime' => null,
        ]);
        UserFactory::new()->create(['username' => 'autre_personne', 'email' => 'autre@email.com']);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/users?search=contact.utile');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminUser',
            '@id' => '/api/admin/users',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/admin/users/' . $target->id,
                    '@type' => 'AdminUser',
                    'id' => (string) $target->id,
                    'username' => 'jean_michel',
                    'email' => 'contact.utile@email.com',
                    'roles' => [],
                    'creation_datetime' => '2026-01-15T09:00:00+00:00',
                    'is_deleted' => false,
                    'is_email_confirmed' => false,
                    'has_musician_profile' => false,
                    'has_teacher_profile' => false,
                ],
            ],
            'view' => ['@id' => '/api/admin/users?search=contact.utile', '@type' => 'PartialCollectionView'],
        ]);
    }

    /**
     * The endpoint answers a question about one person. Without a term it would page through the
     * whole user base instead, which is a different feature and not one anybody asked for.
     */
    public function test_no_search_term_lists_nobody(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        UserFactory::new()->many(3)->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/users');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminUser',
            '@id' => '/api/admin/users',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
        ]);
    }

    /**
     * A LIKE wildcard in the term would otherwise turn the search into the listing the test above
     * refuses to be: unescaped, `%%` matches every row and `__` matches every name of two
     * characters or more.
     *
     * Usernames are pinned rather than faked so neither term can match one by accident.
     */
    #[DataProvider('likeWildcardProvider')]
    public function test_a_like_wildcard_is_not_a_way_to_list_everyone(string $encodedTerm): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        UserFactory::new()->create(['username' => 'premier', 'email' => 'premier@email.com']);
        UserFactory::new()->create(['username' => 'deuxieme', 'email' => 'deuxieme@email.com']);
        UserFactory::new()->create(['username' => 'troisieme', 'email' => 'troisieme@email.com']);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/users?search=' . $encodedTerm);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminUser',
            '@id' => '/api/admin/users',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
            'view' => ['@id' => '/api/admin/users?search=' . $encodedTerm, '@type' => 'PartialCollectionView'],
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function likeWildcardProvider(): iterable
    {
        yield 'percent matches every row' => ['%25%25'];
        yield 'underscore matches every name' => ['__'];
    }

    public function test_a_deleted_account_is_still_findable(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $target = UserFactory::new()->create([
            'username' => 'compte_ferme',
            'email' => 'ferme@email.com',
            'roles' => [],
            'creationDatetime' => new \DateTime('2026-01-15 09:00:00'),
            'lastLoginDatetime' => null,
            'deletionDatetime' => new \DateTimeImmutable('2026-08-01 10:00:00'),
        ]);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/users?search=compte_ferme');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminUser',
            '@id' => '/api/admin/users',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/admin/users/' . $target->id,
                    '@type' => 'AdminUser',
                    'id' => (string) $target->id,
                    'username' => 'compte_ferme',
                    'email' => 'ferme@email.com',
                    'roles' => [],
                    'creation_datetime' => '2026-01-15T09:00:00+00:00',
                    'deletion_datetime' => '2026-08-01T10:00:00+00:00',
                    'is_deleted' => true,
                    'is_email_confirmed' => false,
                    'has_musician_profile' => false,
                    'has_teacher_profile' => false,
                ],
            ],
            'view' => ['@id' => '/api/admin/users?search=compte_ferme', '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_a_short_search_term_is_refused(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/users?search=a');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'search',
                    'message' => 'Saisissez au moins 2 caractères',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
            'detail' => 'search: Saisissez au moins 2 caractères',
            'type' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            'title' => 'An error occurred',
            'description' => 'search: Saisissez au moins 2 caractères',
        ]);
    }
}
