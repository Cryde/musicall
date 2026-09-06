<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\User;

use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class AdminUserRoleUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_not_logged(): void
    {
        $target = UserFactory::new()->asBaseUser()->create();

        $this->client->jsonRequest('POST', '/api/admin/users/' . $target->id . '/roles', ['roles' => ['ROLE_TESTER']], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_base_user_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $target = UserFactory::new()->create(['username' => 'target', 'email' => 'target@email.com']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/admin/users/' . $target->id . '/roles', ['roles' => ['ROLE_TESTER']], self::HEADERS);
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

        $this->assertSame([], $this->reload((string) $target->id)->roles);
    }

    public function test_admin_grants_the_tester_role(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $target = UserFactory::new()->create(['username' => 'target', 'email' => 'target@email.com', 'roles' => []]);

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/users/' . $target->id . '/roles', ['roles' => ['ROLE_TESTER']], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->assertSame(['ROLE_TESTER'], $this->reload((string) $target->id)->roles);
    }

    public function test_sending_an_empty_list_revokes_everything_grantable(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $target = UserFactory::new()->create([
            'username' => 'target',
            'email' => 'target@email.com',
            'roles' => ['ROLE_TESTER', 'ROLE_ADMIN'],
        ]);

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/users/' . $target->id . '/roles', ['roles' => []], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->assertSame([], $this->reload((string) $target->id)->roles);
    }

    /**
     * The endpoint owns the grantable set, not the whole column, so a role it cannot hand out is
     * not something it may quietly take away either.
     */
    public function test_a_role_outside_the_grantable_set_is_preserved(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $target = UserFactory::new()->create([
            'username' => 'target',
            'email' => 'target@email.com',
            'roles' => ['ROLE_LEGACY_SOMETHING', 'ROLE_TESTER'],
        ]);

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/users/' . $target->id . '/roles', ['roles' => ['ROLE_ADMIN']], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->assertSame(['ROLE_LEGACY_SOMETHING', 'ROLE_ADMIN'], $this->reload((string) $target->id)->roles);
    }

    /**
     * The guardrail that matters: an admin dropping their own ROLE_ADMIN would lock themselves out
     * of a back office only another admin could let them back into.
     */
    public function test_an_admin_cannot_change_their_own_roles(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/users/' . $admin->id . '/roles', ['roles' => []], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous ne pouvez pas modifier vos propres rôles',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous ne pouvez pas modifier vos propres rôles',
        ]);

        $this->assertSame(['ROLE_ADMIN'], $this->reload((string) $admin->id)->roles);
    }

    public function test_an_ungrantable_role_is_refused(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $target = UserFactory::new()->create(['username' => 'target', 'email' => 'target@email.com', 'roles' => []]);

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/users/' . $target->id . '/roles', ['roles' => ['ROLE_SUPER_ADMIN']], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'roles[0]',
                    'message' => 'Rôle inconnu ou non attribuable',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => 'roles[0]: Rôle inconnu ou non attribuable',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
            'description' => 'roles[0]: Rôle inconnu ou non attribuable',
        ]);

        $this->assertSame([], $this->reload((string) $target->id)->roles);
    }

    public function test_a_malformed_id_is_a_404_rather_than_a_500(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/users/not-a-uuid/roles', ['roles' => []], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Utilisateur introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Utilisateur introuvable',
        ]);
    }

    public function test_duplicate_roles_are_refused(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $target = UserFactory::new()->create(['username' => 'target', 'email' => 'target@email.com', 'roles' => []]);

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/users/' . $target->id . '/roles', ['roles' => ['ROLE_TESTER', 'ROLE_TESTER']], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/7911c98d-b845-4da0-94b7-a8dac36bc55a',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'roles',
                    'message' => 'Un rôle est répété',
                    'code' => '7911c98d-b845-4da0-94b7-a8dac36bc55a',
                ],
            ],
            'detail' => 'roles: Un rôle est répété',
            'type' => '/validation_errors/7911c98d-b845-4da0-94b7-a8dac36bc55a',
            'title' => 'An error occurred',
            'description' => 'roles: Un rôle est répété',
        ]);

        $this->assertSame([], $this->reload((string) $target->id)->roles);
    }

    /**
     * Assert\Unique scans the array once per element, so an unbounded payload would burn CPU
     * quadratically before anything rejected it.
     */
    public function test_too_many_roles_are_refused(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $target = UserFactory::new()->create(['username' => 'target', 'email' => 'target@email.com', 'roles' => []]);

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/users/' . $target->id . '/roles', [
            'roles' => ['ROLE_TESTER', 'ROLE_ADMIN', 'ROLE_AUTRE'],
        ], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame([], $this->reload((string) $target->id)->roles);
    }

    private function reload(string $id): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $user = $entityManager->getRepository(User::class)->find($id);
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }
}
