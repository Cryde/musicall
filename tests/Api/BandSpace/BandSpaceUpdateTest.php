<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BandSpaceUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_an_admin_renames_the_space(): void
    {
        [$admin, $bandSpace] = $this->spaceWithAdmin();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id,
            ['name' => 'Les Nouveaux Rockeurs'],
            self::HEADERS,
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpace',
            '@id' => '/api/band_spaces/' . $bandSpace->id,
            '@type' => 'BandSpace',
            'id' => $bandSpace->id,
            'name' => 'Les Nouveaux Rockeurs',
            'role' => 'admin',
            'deletion_scheduled_datetime' => null,
        ]);

        $reloaded = $this->reload($bandSpace);
        $this->assertSame('Les Nouveaux Rockeurs', $reloaded->name);

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($reloaded, BandSpaceModule::Settings, $reloaded->id);
        $this->assertCount(1, $activities);
        $this->assertSame('band_renamed', $activities[0]->type);
        $this->assertSame(['from' => 'The Rockers', 'to' => 'Les Nouveaux Rockeurs'], $activities[0]->payload);
        $this->assertSame($admin->id, $activities[0]->actor?->id);
    }

    public function test_the_new_name_is_trimmed(): void
    {
        [$admin, $bandSpace] = $this->spaceWithAdmin();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id,
            ['name' => '   Les Nouveaux Rockeurs   '],
            self::HEADERS,
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpace',
            '@id' => '/api/band_spaces/' . $bandSpace->id,
            '@type' => 'BandSpace',
            'id' => $bandSpace->id,
            'name' => 'Les Nouveaux Rockeurs',
            'role' => 'admin',
            'deletion_scheduled_datetime' => null,
        ]);

        $this->assertSame('Les Nouveaux Rockeurs', $this->reload($bandSpace)->name);
    }

    /**
     * A rename that changes nothing must not pile up an activity row every time the form is submitted.
     */
    public function test_renaming_to_the_same_name_records_no_activity(): void
    {
        [$admin, $bandSpace] = $this->spaceWithAdmin();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id,
            ['name' => 'The Rockers'],
            self::HEADERS,
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpace',
            '@id' => '/api/band_spaces/' . $bandSpace->id,
            '@type' => 'BandSpace',
            'id' => $bandSpace->id,
            'name' => 'The Rockers',
            'role' => 'admin',
            'deletion_scheduled_datetime' => null,
        ]);

        $reloaded = $this->reload($bandSpace);
        $this->assertCount(0, self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($reloaded, BandSpaceModule::Settings, $reloaded->id));
    }

    public function test_a_plain_member_cannot_rename_the_space(): void
    {
        [, $bandSpace] = $this->spaceWithAdmin();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@example.com']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id,
            ['name' => 'Le groupe du batteur'],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous devez être administrateur pour effectuer cette action',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous devez être administrateur pour effectuer cette action',
        ]);

        $this->assertSame('The Rockers', $this->reload($bandSpace)->name);
    }

    public function test_a_non_member_cannot_rename_the_space(): void
    {
        [, $bandSpace] = $this->spaceWithAdmin();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@example.com']);

        $this->client->loginUser($outsider);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id,
            ['name' => 'Mon groupe maintenant'],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'You are not a member of this band space',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'You are not a member of this band space',
        ]);

        $this->assertSame('The Rockers', $this->reload($bandSpace)->name);
    }

    public function test_an_anonymous_request_cannot_rename_the_space(): void
    {
        [, $bandSpace] = $this->spaceWithAdmin();

        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id,
            ['name' => 'Groupe anonyme'],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);

        $this->assertSame('The Rockers', $this->reload($bandSpace)->name);
    }

    public function test_a_whitespace_only_name_is_refused(): void
    {
        [$admin, $bandSpace] = $this->spaceWithAdmin();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id,
            ['name' => '   '],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => "name: Veuillez spécifier un nom\nname: Le nom doit contenir au moins 3 caractères",
            'description' => "name: Veuillez spécifier un nom\nname: Le nom doit contenir au moins 3 caractères",
            'status' => 422,
            'type' => '/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=9ff3fdc4-b214-49db-8718-39c315e33d45',
            'violations' => [
                [
                    'propertyPath' => 'name',
                    'message' => 'Veuillez spécifier un nom',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'name',
                    'message' => 'Le nom doit contenir au moins 3 caractères',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
        ]);

        $this->assertSame('The Rockers', $this->reload($bandSpace)->name);
    }

    /**
     * The case the trim normalizer exists for: padding must not buy a name its way past the minimum,
     * because the padding is gone by the time the processor stores it.
     */
    public function test_a_padded_name_that_trims_below_the_minimum_is_refused(): void
    {
        [$admin, $bandSpace] = $this->spaceWithAdmin();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id,
            ['name' => '  ab  '],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'name: Le nom doit contenir au moins 3 caractères',
            'description' => 'name: Le nom doit contenir au moins 3 caractères',
            'status' => 422,
            'type' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            'violations' => [
                [
                    'propertyPath' => 'name',
                    'message' => 'Le nom doit contenir au moins 3 caractères',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
        ]);

        $this->assertSame('The Rockers', $this->reload($bandSpace)->name);
    }

    public function test_an_over_long_name_is_refused(): void
    {
        [$admin, $bandSpace] = $this->spaceWithAdmin();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id,
            ['name' => str_repeat('a', 256)],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'name: Le nom ne peut pas dépasser 255 caractères',
            'description' => 'name: Le nom ne peut pas dépasser 255 caractères',
            'status' => 422,
            'type' => '/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'violations' => [
                [
                    'propertyPath' => 'name',
                    'message' => 'Le nom ne peut pas dépasser 255 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
            ],
        ]);

        $this->assertSame('The Rockers', $this->reload($bandSpace)->name);
    }

    public function test_a_space_pending_deletion_cannot_be_renamed(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create([
            'name' => 'The Rockers',
            'deletionScheduledDatetime' => new \DateTimeImmutable('+30 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id,
            ['name' => 'Groupe condamné'],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
        ]);

        $this->assertSame('The Rockers', $this->reload($bandSpace)->name);
    }

    /**
     * @return array{User, BandSpace}
     */
    private function spaceWithAdmin(): array
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        return [$admin, $bandSpace];
    }

    /**
     * The request reboots the kernel, so the entity held by the test belongs to a dead entity manager.
     */
    private function reload(BandSpace $bandSpace): BandSpace
    {
        $reloaded = self::getContainer()->get(BandSpaceRepository::class)
            ->findOneByIdWithMemberships((string) $bandSpace->id);
        $this->assertInstanceOf(BandSpace::class, $reloaded);

        return $reloaded;
    }
}
