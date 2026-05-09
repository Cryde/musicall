<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceInvitationFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceInvitationCreateTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_create_invitation_by_email_for_new_user(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/invitations',
            ['identifier' => 'newuser@example.com'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $invitationRepo = self::getContainer()->get(BandSpaceInvitationRepository::class);
        $invitation = $invitationRepo->findPendingByEmailAndBandSpace('newuser@example.com', $bandSpace);
        $this->assertNotNull($invitation);
        $this->assertNull($invitation->existingUser);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceInvitation',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/invitations/' . $invitation->id,
            '@type' => 'BandSpaceInvitation',
            'id' => $invitation->id,
            'band_space_id' => $bandSpace->id,
            'email' => 'newuser@example.com',
            'status' => 'pending',
            'creation_datetime' => $invitation->creationDatetime->format(\DateTimeInterface::ATOM),
            'expiration_datetime' => $invitation->expirationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Settings, $invitation->id);
        $this->assertCount(1, $activities);
        $this->assertSame('invitation_sent', $activities[0]->type);
        $this->assertSame(
            ['email' => 'newuser@example.com', 'invited_user_id' => null, 'invited_username' => null],
            $activities[0]->payload,
        );
        $this->assertSame($admin->id, $activities[0]->actor?->id);
    }

    public function test_create_invitation_by_email_for_existing_user(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $existingUser = UserFactory::new()->create(['username' => 'existing', 'email' => 'existing@example.com']);
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/invitations',
            ['identifier' => 'existing@example.com'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $invitationRepo = self::getContainer()->get(BandSpaceInvitationRepository::class);
        $invitation = $invitationRepo->findPendingByEmailAndBandSpace('existing@example.com', $bandSpace);
        $this->assertNotNull($invitation);
        $this->assertNotNull($invitation->existingUser);
        $this->assertSame($existingUser->id, $invitation->existingUser->id);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceInvitation',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/invitations/' . $invitation->id,
            '@type' => 'BandSpaceInvitation',
            'id' => $invitation->id,
            'band_space_id' => $bandSpace->id,
            'email' => 'existing@example.com',
            'status' => 'pending',
            'creation_datetime' => $invitation->creationDatetime->format(\DateTimeInterface::ATOM),
            'expiration_datetime' => $invitation->expirationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_create_invitation_by_username(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $targetUser = UserFactory::new()->create(['username' => 'guitarist42', 'email' => 'guitarist@example.com']);
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/invitations',
            ['identifier' => 'guitarist42'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $invitationRepo = self::getContainer()->get(BandSpaceInvitationRepository::class);
        $invitation = $invitationRepo->findPendingByEmailAndBandSpace('guitarist@example.com', $bandSpace);
        $this->assertNotNull($invitation);
        $this->assertSame($targetUser->id, $invitation->existingUser->id);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceInvitation',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/invitations/' . $invitation->id,
            '@type' => 'BandSpaceInvitation',
            'id' => $invitation->id,
            'band_space_id' => $bandSpace->id,
            'email' => 'guitarist@example.com',
            'status' => 'pending',
            'creation_datetime' => $invitation->creationDatetime->format(\DateTimeInterface::ATOM),
            'expiration_datetime' => $invitation->expirationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_invite_by_username_not_found(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/invitations',
            ['identifier' => 'nonexistent_user'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_c2d3e4f5-6a7b-8c9d-0e1f-2a3b4c5d6e7f',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'identifier',
                    'message' => 'Aucun utilisateur trouvé avec ce nom d\'utilisateur',
                    'code' => 'music_all_c2d3e4f5-6a7b-8c9d-0e1f-2a3b4c5d6e7f',
                ],
            ],
            'detail' => 'identifier: Aucun utilisateur trouvé avec ce nom d\'utilisateur',
            'description' => 'identifier: Aucun utilisateur trouvé avec ce nom d\'utilisateur',
            'type' => '/validation_errors/music_all_c2d3e4f5-6a7b-8c9d-0e1f-2a3b4c5d6e7f',
            'title' => 'An error occurred',
        ]);
    }

    public function test_invite_by_username_already_member(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/invitations',
            ['identifier' => 'member'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet utilisateur est déjà membre de ce Band Space',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cet utilisateur est déjà membre de ce Band Space',
        ]);
    }

    public function test_cannot_invite_existing_member_by_email(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/invitations',
            ['identifier' => 'member@example.com'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet utilisateur est déjà membre de ce Band Space',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cet utilisateur est déjà membre de ce Band Space',
        ]);
    }

    public function test_cannot_invite_duplicate_pending(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invited@example.com',
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/invitations',
            ['identifier' => 'invited@example.com'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Une invitation est déjà en attente pour cet utilisateur',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Une invitation est déjà en attente pour cet utilisateur',
        ]);
    }

    public function test_non_admin_cannot_invite(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/invitations',
            ['identifier' => 'newuser@example.com'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
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
    }

    public function test_empty_identifier_returns_validation_error(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/invitations',
            ['identifier' => ''],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'identifier',
                    'message' => 'Veuillez spécifier une adresse email ou un nom d\'utilisateur',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'identifier: Veuillez spécifier une adresse email ou un nom d\'utilisateur',
            'description' => 'identifier: Veuillez spécifier une adresse email ou un nom d\'utilisateur',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
        ]);
    }

    public function test_admin_cannot_invite_self_by_email(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/invitations',
            ['identifier' => $admin->email],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet utilisateur est déjà membre de ce Band Space',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cet utilisateur est déjà membre de ce Band Space',
        ]);
    }

    public function test_admin_cannot_invite_self_by_username(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/invitations',
            ['identifier' => $admin->username],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet utilisateur est déjà membre de ce Band Space',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cet utilisateur est déjà membre de ce Band Space',
        ]);
    }

    public function test_invalid_email_returns_error(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/invitations',
            ['identifier' => 'not-a-valid@'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_b1c2d3e4-5f6a-7b8c-9d0e-1f2a3b4c5d6e',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'identifier',
                    'message' => 'L\'adresse email n\'est pas valide',
                    'code' => 'music_all_b1c2d3e4-5f6a-7b8c-9d0e-1f2a3b4c5d6e',
                ],
            ],
            'detail' => 'identifier: L\'adresse email n\'est pas valide',
            'description' => 'identifier: L\'adresse email n\'est pas valide',
            'type' => '/validation_errors/music_all_b1c2d3e4-5f6a-7b8c-9d0e-1f2a3b4c5d6e',
            'title' => 'An error occurred',
        ]);
    }
}
