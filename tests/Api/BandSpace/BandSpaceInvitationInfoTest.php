<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\InvitationStatus;
use App\Enum\BandSpace\Role;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceInvitationFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceInvitationInfoTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_invitation_info(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invited@example.com',
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->request('GET', '/api/band_spaces/invitations/' . $invitation->token . '/info');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceInvitationInfo',
            '@id' => '/api/band_spaces/invitations/' . $invitation->token . '/info',
            '@type' => 'BandSpaceInvitationInfo',
            'token' => $invitation->token,
            'email' => 'invited@example.com',
            'band_space_name' => 'The Rockers',
        ]);
    }

    public function test_get_invitation_info_invalid_token(): void
    {
        $this->client->request('GET', '/api/band_spaces/invitations/invalidtoken123/info');

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

    public function test_get_invitation_info_expired_token(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invited@example.com',
            'expirationDatetime' => (new \DateTime())->modify('-1 day'),
        ])->create();

        $this->client->request('GET', '/api/band_spaces/invitations/' . $invitation->token . '/info');

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

    public function test_get_invitation_info_already_accepted_to_a_visitor(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invited@example.com',
            'status' => InvitationStatus::Accepted,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->request('GET', '/api/band_spaces/invitations/' . $invitation->token . '/info');

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

    /** The member an invitation brought in at sign-up comes back to it through the return path (#1359). */
    public function test_get_invitation_info_already_accepted_by_the_viewer(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $invitee = UserFactory::new()->create(['username' => 'invitee', 'email' => 'invitee@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $invitee, 'role' => Role::User])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invitee@example.com',
            'status' => InvitationStatus::Accepted,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($invitee);
        $this->client->request('GET', '/api/band_spaces/invitations/' . $invitation->token . '/info');

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Tu es déjà membre de ce Band Space',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Tu es déjà membre de ce Band Space',
        ]);
    }

    /** Signed in but not in the band: the spent invitation says nothing more than to a visitor. */
    public function test_get_invitation_info_already_accepted_seen_by_a_non_member(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $stranger = UserFactory::new()->create(['username' => 'stranger', 'email' => 'stranger@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invited@example.com',
            'status' => InvitationStatus::Accepted,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($stranger);
        $this->client->request('GET', '/api/band_spaces/invitations/' . $invitation->token . '/info');

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

    /** A second invitation to a band the viewer is already in: they would only read a 409 on accept. */
    public function test_get_invitation_info_still_pending_seen_by_a_member(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'member@example.com',
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/invitations/' . $invitation->token . '/info');

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Tu es déjà membre de ce Band Space',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Tu es déjà membre de ce Band Space',
        ]);
    }

    /** Expired or not, a member is told they are in rather than asked to get a new invitation. */
    public function test_get_invitation_info_expired_seen_by_a_member(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'member@example.com',
            'expirationDatetime' => (new \DateTime())->modify('-1 day'),
        ])->create();

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/invitations/' . $invitation->token . '/info');

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Tu es déjà membre de ce Band Space',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Tu es déjà membre de ce Band Space',
        ]);
    }

    /** A declined invitation to a band the viewer joined since. */
    public function test_get_invitation_info_declined_seen_by_a_member(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'member@example.com',
            'status' => InvitationStatus::Declined,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/invitations/' . $invitation->token . '/info');

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Tu es déjà membre de ce Band Space',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Tu es déjà membre de ce Band Space',
        ]);
    }
}
