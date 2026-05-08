<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\InvitationStatus;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceInvitationFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceInvitationAcceptTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_accept_invitation_existing_user(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $invitee = UserFactory::new()->create(['username' => 'invitee', 'email' => 'invitee@example.com']);
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invitee@example.com',
            'existingUser' => $invitee,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($invitee->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/invitations/' . $invitation->_real()->token . '/accept',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceInvitationAccept',
            '@id' => '/api/band_space_invitation_accepts/' . $invitation->_real()->token,
            '@type' => 'BandSpaceInvitationAccept',
            'token' => $invitation->_real()->token,
            'band_space_id' => $bandSpace->_real()->id,
            'band_space_name' => 'The Rockers',
        ]);

        $membershipRepo = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertTrue($membershipRepo->isMember($bandSpace->_real(), $invitee->_real()));

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace->_real(), BandSpaceModule::Settings, $invitation->_real()->id);
        $this->assertCount(1, $activities);
        $this->assertSame('invitation_accepted', $activities[0]->type);
        $this->assertSame(
            ['email' => 'invitee@example.com', 'invited_user_id' => $invitee->_real()->id, 'invited_username' => 'invitee'],
            $activities[0]->payload,
        );
        $this->assertSame($invitee->_real()->id, $activities[0]->actor?->id);
    }

    public function test_accept_invitation_email_match(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $invitee = UserFactory::new()->create(['username' => 'invitee', 'email' => 'invitee@example.com']);
        $bandSpace = BandSpaceFactory::new(['name' => 'Jazz Band'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invitee@example.com',
            'existingUser' => null,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($invitee->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/invitations/' . $invitation->_real()->token . '/accept',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceInvitationAccept',
            '@id' => '/api/band_space_invitation_accepts/' . $invitation->_real()->token,
            '@type' => 'BandSpaceInvitationAccept',
            'token' => $invitation->_real()->token,
            'band_space_id' => $bandSpace->_real()->id,
            'band_space_name' => 'Jazz Band',
        ]);

        $membershipRepo = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertTrue($membershipRepo->isMember($bandSpace->_real(), $invitee->_real()));
    }

    public function test_accept_expired_invitation(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $invitee = UserFactory::new()->create(['username' => 'invitee', 'email' => 'invitee@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invitee@example.com',
            'existingUser' => $invitee,
            'expirationDatetime' => (new \DateTime())->modify('-1 day'),
        ])->create();

        $this->client->loginUser($invitee->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/invitations/' . $invitation->_real()->token . '/accept',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Invitation introuvable ou expirée',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Invitation introuvable ou expirée',
        ]);
    }

    public function test_accept_invitation_wrong_user(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $invitee = UserFactory::new()->create(['username' => 'invitee', 'email' => 'invitee@example.com']);
        $wrongUser = UserFactory::new()->create(['username' => 'wrong', 'email' => 'wrong@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invitee@example.com',
            'existingUser' => $invitee,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($wrongUser->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/invitations/' . $invitation->_real()->token . '/accept',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cette invitation ne vous est pas destinée',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Cette invitation ne vous est pas destinée',
        ]);
    }

    public function test_accept_already_accepted_invitation(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $invitee = UserFactory::new()->create(['username' => 'invitee', 'email' => 'invitee@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invitee@example.com',
            'existingUser' => $invitee,
            'status' => InvitationStatus::Accepted,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($invitee->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/invitations/' . $invitation->_real()->token . '/accept',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Invitation introuvable ou expirée',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Invitation introuvable ou expirée',
        ]);
    }
}
