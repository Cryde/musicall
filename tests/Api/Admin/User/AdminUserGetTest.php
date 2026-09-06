<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\User;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class AdminUserGetTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $target = UserFactory::new()->asBaseUser()->create();

        $this->client->request('GET', '/api/admin/users/' . $target->id);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_base_user_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/users/' . $user->id);
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

    public function test_admin_sees_the_summary_and_the_activity_counts(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $target = UserFactory::new()->create([
            'username' => 'jean_michel',
            'email' => 'jm@email.com',
            'roles' => ['ROLE_TESTER'],
            'creationDatetime' => new \DateTime('2026-01-15 09:00:00'),
            'lastLoginDatetime' => new \DateTime('2026-09-01 08:00:00'),
            'confirmationDatetime' => new \DateTime('2026-01-15 09:30:00'),
        ]);
        PublicationFactory::new()->create(['author' => $target, 'slug' => 'une-publication']);
        PublicationFactory::new()->create(['author' => $target, 'slug' => 'une-autre-publication']);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/users/' . $target->id);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminUser',
            '@id' => '/api/admin/users/' . $target->id,
            '@type' => 'AdminUser',
            'id' => (string) $target->id,
            'username' => 'jean_michel',
            'email' => 'jm@email.com',
            'roles' => ['ROLE_TESTER'],
            'creation_datetime' => '2026-01-15T09:00:00+00:00',
            'last_login_datetime' => '2026-09-01T08:00:00+00:00',
            'confirmation_datetime' => '2026-01-15T09:30:00+00:00',
            'is_deleted' => false,
            'is_email_confirmed' => true,
            'has_musician_profile' => false,
            'has_teacher_profile' => false,
            'activity' => [
                '@type' => 'AdminUserActivity',
                'publications' => 2,
                'comments' => 0,
                'forum_posts' => 0,
                'musician_announces' => 0,
                'band_spaces' => 0,
            ],
        ]);
    }

    /**
     * Closing an account anonymises it rather than removing the row, so an admin following up on a
     * report has to be able to reach it instead of getting a 404.
     */
    public function test_a_deleted_account_is_still_visible(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $target = UserFactory::new()->create([
            'username' => 'compte_ferme',
            'email' => 'ferme@email.com',
            'roles' => [],
            'creationDatetime' => new \DateTime('2026-01-15 09:00:00'),
            'lastLoginDatetime' => new \DateTime('2026-02-01 08:00:00'),
            'deletionDatetime' => new \DateTimeImmutable('2026-08-01 10:00:00'),
        ]);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/users/' . $target->id);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminUser',
            '@id' => '/api/admin/users/' . $target->id,
            '@type' => 'AdminUser',
            'id' => (string) $target->id,
            'username' => 'compte_ferme',
            'email' => 'ferme@email.com',
            'roles' => [],
            'creation_datetime' => '2026-01-15T09:00:00+00:00',
            'last_login_datetime' => '2026-02-01T08:00:00+00:00',
            'deletion_datetime' => '2026-08-01T10:00:00+00:00',
            'is_deleted' => true,
            'is_email_confirmed' => false,
            'has_musician_profile' => false,
            'has_teacher_profile' => false,
            'activity' => [
                '@type' => 'AdminUserActivity',
                'publications' => 0,
                'comments' => 0,
                'forum_posts' => 0,
                'musician_announces' => 0,
                'band_spaces' => 0,
            ],
        ]);
    }

    public function test_a_malformed_id_is_a_404_rather_than_a_500(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/users/not-a-uuid');
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

    public function test_an_unknown_id_is_a_404(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/users/1b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4bed');
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
}
