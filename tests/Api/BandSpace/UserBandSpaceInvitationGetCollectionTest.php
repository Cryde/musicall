<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\InvitationStatus;
use App\Enum\BandSpace\MembershipStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceInvitationFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class UserBandSpaceInvitationGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_it_lists_an_invitation_to_the_callers_account(): void
    {
        $caller = UserFactory::new()->asBaseUser()->create();
        $inviter = UserFactory::new()->create(['username' => 'louise', 'email' => 'louise@example.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        $invitation = BandSpaceInvitationFactory::new()->create([
            'bandSpace' => $bandSpace,
            'invitedBy' => $inviter,
            'email' => 'base_user@email.com',
            'existingUser' => $caller,
            'invitedByUsername' => true,
            'creationDatetime' => new \DateTime('2026-06-01 12:00:00'),
        ]);

        $this->client->loginUser($caller);
        $this->client->request('GET', '/api/user/band-space-invitations');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserBandSpaceInvitation',
            '@id' => '/api/user/band-space-invitations',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/user/band-space-invitations/' . $invitation->id,
                    '@type' => 'UserBandSpaceInvitation',
                    'id' => $invitation->id,
                    'token' => $invitation->token,
                    'band_space_id' => $bandSpace->id,
                    'band_space_name' => 'The Rockers',
                    'invited_by_username' => 'louise',
                    'creation_datetime' => '2026-06-01T12:00:00+00:00',
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    /** No account had the address when it was invited, so only the email names the recipient. */
    public function test_it_lists_an_invitation_to_the_callers_address_whatever_its_case(): void
    {
        $caller = UserFactory::new()->asBaseUser()->create(['email' => 'Drummer@Example.com']);
        $inviter = UserFactory::new()->create(['username' => 'louise', 'email' => 'louise@example.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        $invitation = BandSpaceInvitationFactory::new()->create([
            'bandSpace' => $bandSpace,
            'invitedBy' => $inviter,
            'email' => 'drummer@example.com',
            'existingUser' => null,
            'creationDatetime' => new \DateTime('2026-06-01 12:00:00'),
        ]);

        $this->client->loginUser($caller);
        $this->client->request('GET', '/api/user/band-space-invitations');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserBandSpaceInvitation',
            '@id' => '/api/user/band-space-invitations',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/user/band-space-invitations/' . $invitation->id,
                    '@type' => 'UserBandSpaceInvitation',
                    'id' => $invitation->id,
                    'token' => $invitation->token,
                    'band_space_id' => $bandSpace->id,
                    'band_space_name' => 'The Rockers',
                    'invited_by_username' => 'louise',
                    'creation_datetime' => '2026-06-01T12:00:00+00:00',
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_the_newest_invitation_comes_first(): void
    {
        $caller = UserFactory::new()->asBaseUser()->create();
        $louise = UserFactory::new()->create(['username' => 'louise', 'email' => 'louise@example.com']);
        $marc = UserFactory::new()->create(['username' => 'marc', 'email' => 'marc@example.com']);
        $older = BandSpaceInvitationFactory::new()->create([
            'bandSpace' => BandSpaceFactory::new(['name' => 'Older Band']),
            'invitedBy' => $louise,
            'email' => 'base_user@email.com',
            'existingUser' => $caller,
            'creationDatetime' => new \DateTime('2026-06-01 12:00:00'),
        ]);
        $newer = BandSpaceInvitationFactory::new()->create([
            'bandSpace' => BandSpaceFactory::new(['name' => 'Newer Band']),
            'invitedBy' => $marc,
            'email' => 'base_user@email.com',
            'existingUser' => $caller,
            'creationDatetime' => new \DateTime('2026-06-03 09:30:00'),
        ]);

        $this->client->loginUser($caller);
        $this->client->request('GET', '/api/user/band-space-invitations');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserBandSpaceInvitation',
            '@id' => '/api/user/band-space-invitations',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/user/band-space-invitations/' . $newer->id,
                    '@type' => 'UserBandSpaceInvitation',
                    'id' => $newer->id,
                    'token' => $newer->token,
                    'band_space_id' => $newer->bandSpace->id,
                    'band_space_name' => 'Newer Band',
                    'invited_by_username' => 'marc',
                    'creation_datetime' => '2026-06-03T09:30:00+00:00',
                ],
                [
                    '@id' => '/api/user/band-space-invitations/' . $older->id,
                    '@type' => 'UserBandSpaceInvitation',
                    'id' => $older->id,
                    'token' => $older->token,
                    'band_space_id' => $older->bandSpace->id,
                    'band_space_name' => 'Older Band',
                    'invited_by_username' => 'louise',
                    'creation_datetime' => '2026-06-01T12:00:00+00:00',
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    /**
     * Includes an invitation carrying the caller's address but naming another account: accept refuses
     * it (not_recipient), so the list must not offer it.
     */
    public function test_invitations_addressed_to_someone_else_are_not_listed(): void
    {
        $caller = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other', 'email' => 'other@example.com']);
        $inviter = UserFactory::new()->create(['username' => 'louise', 'email' => 'louise@example.com']);
        $listed = BandSpaceInvitationFactory::new()->create([
            'bandSpace' => BandSpaceFactory::new(['name' => 'The Rockers']),
            'invitedBy' => $inviter,
            'email' => 'base_user@email.com',
            'existingUser' => $caller,
            'creationDatetime' => new \DateTime('2026-06-01 12:00:00'),
        ]);
        BandSpaceInvitationFactory::new()->create([
            'invitedBy' => $inviter,
            'email' => 'other@example.com',
            'existingUser' => $other,
        ]);
        BandSpaceInvitationFactory::new()->create([
            'invitedBy' => $inviter,
            'email' => 'stranger@example.com',
            'existingUser' => null,
        ]);
        BandSpaceInvitationFactory::new()->create([
            'invitedBy' => $inviter,
            'email' => 'base_user@email.com',
            'existingUser' => $other,
        ]);

        $this->client->loginUser($caller);
        $this->client->request('GET', '/api/user/band-space-invitations');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserBandSpaceInvitation',
            '@id' => '/api/user/band-space-invitations',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/user/band-space-invitations/' . $listed->id,
                    '@type' => 'UserBandSpaceInvitation',
                    'id' => $listed->id,
                    'token' => $listed->token,
                    'band_space_id' => $listed->bandSpace->id,
                    'band_space_name' => 'The Rockers',
                    'invited_by_username' => 'louise',
                    'creation_datetime' => '2026-06-01T12:00:00+00:00',
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_answered_revoked_and_expired_invitations_are_not_listed(): void
    {
        $caller = UserFactory::new()->asBaseUser()->create();
        $inviter = UserFactory::new()->create(['username' => 'louise', 'email' => 'louise@example.com']);
        $listed = BandSpaceInvitationFactory::new()->create([
            'bandSpace' => BandSpaceFactory::new(['name' => 'The Rockers']),
            'invitedBy' => $inviter,
            'email' => 'base_user@email.com',
            'existingUser' => $caller,
            'creationDatetime' => new \DateTime('2026-06-01 12:00:00'),
        ]);
        foreach ([InvitationStatus::Accepted, InvitationStatus::Declined, InvitationStatus::Revoked, InvitationStatus::Expired] as $status) {
            BandSpaceInvitationFactory::new()->create([
                'invitedBy' => $inviter,
                'email' => 'base_user@email.com',
                'existingUser' => $caller,
                'status' => $status,
            ]);
        }
        // Still pending because app:band-space:expire-invitations has not run yet, but past its date.
        BandSpaceInvitationFactory::new()->create([
            'invitedBy' => $inviter,
            'email' => 'base_user@email.com',
            'existingUser' => $caller,
            'expirationDatetime' => (new \DateTime())->modify('-1 minute'),
        ]);

        $this->client->loginUser($caller);
        $this->client->request('GET', '/api/user/band-space-invitations');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserBandSpaceInvitation',
            '@id' => '/api/user/band-space-invitations',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/user/band-space-invitations/' . $listed->id,
                    '@type' => 'UserBandSpaceInvitation',
                    'id' => $listed->id,
                    'token' => $listed->token,
                    'band_space_id' => $listed->bandSpace->id,
                    'band_space_name' => 'The Rockers',
                    'invited_by_username' => 'louise',
                    'creation_datetime' => '2026-06-01T12:00:00+00:00',
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    /** Accept refuses to join a space pending deletion, so offering it would only lead to a 409. */
    public function test_an_invitation_to_a_space_pending_deletion_is_not_listed(): void
    {
        $caller = UserFactory::new()->asBaseUser()->create();
        $inviter = UserFactory::new()->create(['username' => 'louise', 'email' => 'louise@example.com']);
        $listed = BandSpaceInvitationFactory::new()->create([
            'bandSpace' => BandSpaceFactory::new(['name' => 'The Rockers']),
            'invitedBy' => $inviter,
            'email' => 'base_user@email.com',
            'existingUser' => $caller,
            'creationDatetime' => new \DateTime('2026-06-01 12:00:00'),
        ]);
        BandSpaceInvitationFactory::new()->create([
            'bandSpace' => BandSpaceFactory::new(['deletionScheduledDatetime' => new \DateTimeImmutable('+30 days')]),
            'invitedBy' => $inviter,
            'email' => 'base_user@email.com',
            'existingUser' => $caller,
        ]);

        $this->client->loginUser($caller);
        $this->client->request('GET', '/api/user/band-space-invitations');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserBandSpaceInvitation',
            '@id' => '/api/user/band-space-invitations',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/user/band-space-invitations/' . $listed->id,
                    '@type' => 'UserBandSpaceInvitation',
                    'id' => $listed->id,
                    'token' => $listed->token,
                    'band_space_id' => $listed->bandSpace->id,
                    'band_space_name' => 'The Rockers',
                    'invited_by_username' => 'louise',
                    'creation_datetime' => '2026-06-01T12:00:00+00:00',
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    /** A former member is invited back, an active one would only get a 409 already_member. */
    public function test_an_invitation_to_a_space_the_caller_is_in_is_not_listed_but_one_to_a_space_they_left_is(): void
    {
        $caller = UserFactory::new()->asBaseUser()->create();
        $inviter = UserFactory::new()->create(['username' => 'louise', 'email' => 'louise@example.com']);
        $currentBand = BandSpaceFactory::new()->create();
        $formerBand = BandSpaceFactory::new()->create(['name' => 'Former Band']);
        BandSpaceMembershipFactory::new()->create(['bandSpace' => $currentBand, 'user' => $caller]);
        BandSpaceMembershipFactory::new()->create(['bandSpace' => $formerBand, 'user' => $caller, 'status' => MembershipStatus::Left]);
        BandSpaceInvitationFactory::new()->create([
            'bandSpace' => $currentBand,
            'invitedBy' => $inviter,
            'email' => 'base_user@email.com',
            'existingUser' => $caller,
        ]);
        $listed = BandSpaceInvitationFactory::new()->create([
            'bandSpace' => $formerBand,
            'invitedBy' => $inviter,
            'email' => 'base_user@email.com',
            'existingUser' => $caller,
            'creationDatetime' => new \DateTime('2026-06-01 12:00:00'),
        ]);

        $this->client->loginUser($caller);
        $this->client->request('GET', '/api/user/band-space-invitations');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserBandSpaceInvitation',
            '@id' => '/api/user/band-space-invitations',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/user/band-space-invitations/' . $listed->id,
                    '@type' => 'UserBandSpaceInvitation',
                    'id' => $listed->id,
                    'token' => $listed->token,
                    'band_space_id' => $formerBand->id,
                    'band_space_name' => 'Former Band',
                    'invited_by_username' => 'louise',
                    'creation_datetime' => '2026-06-01T12:00:00+00:00',
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    /** Closing an account anonymises it in place, so the raw username would read "deleted_<uuid>". */
    public function test_an_invitation_from_a_closed_account_is_listed_under_the_placeholder(): void
    {
        $caller = UserFactory::new()->asBaseUser()->create();
        $closedAccount = UserFactory::new()->create([
            'username' => 'deleted_0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b',
            'email' => 'deleted_0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b@deleted.local',
            'deletionDatetime' => new \DateTimeImmutable('2026-06-02 08:00:00'),
        ]);
        $invitation = BandSpaceInvitationFactory::new()->create([
            'bandSpace' => BandSpaceFactory::new(['name' => 'The Rockers']),
            'invitedBy' => $closedAccount,
            'email' => 'base_user@email.com',
            'existingUser' => $caller,
            'creationDatetime' => new \DateTime('2026-06-01 12:00:00'),
        ]);

        $this->client->loginUser($caller);
        $this->client->request('GET', '/api/user/band-space-invitations');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserBandSpaceInvitation',
            '@id' => '/api/user/band-space-invitations',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/user/band-space-invitations/' . $invitation->id,
                    '@type' => 'UserBandSpaceInvitation',
                    'id' => $invitation->id,
                    'token' => $invitation->token,
                    'band_space_id' => $invitation->bandSpace->id,
                    'band_space_name' => 'The Rockers',
                    'invited_by_username' => 'Utilisateur supprimé',
                    'creation_datetime' => '2026-06-01T12:00:00+00:00',
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_the_list_is_empty_when_nobody_invited_the_caller(): void
    {
        $caller = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($caller);
        $this->client->request('GET', '/api/user/band-space-invitations');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserBandSpaceInvitation',
            '@id' => '/api/user/band-space-invitations',
            '@type' => 'Collection',
            'member' => [],
            'totalItems' => 0,
        ]);
    }

    public function test_an_anonymous_visitor_is_refused(): void
    {
        $this->client->request('GET', '/api/user/band-space-invitations');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    /** The item path only gives each invitation an @id: even its recipient cannot GET it. */
    public function test_an_invitation_item_is_not_served(): void
    {
        $caller = UserFactory::new()->asBaseUser()->create();
        $invitation = BandSpaceInvitationFactory::new()->create([
            'email' => 'base_user@email.com',
            'existingUser' => $caller,
        ]);

        $this->client->loginUser($caller);
        $this->client->request('GET', '/api/user/band-space-invitations/' . $invitation->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'This route does not aim to be called.',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'This route does not aim to be called.',
        ]);
    }
}
