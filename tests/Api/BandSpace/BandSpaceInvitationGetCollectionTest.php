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
class BandSpaceInvitationGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_list_pending_invitations(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'pending@example.com',
            'creationDatetime' => new \DateTime('2026-06-01 12:00:00'),
            'expirationDatetime' => new \DateTime('2027-06-08 12:00:00'),
        ])->create();

        // Expired invitation (should not appear)
        BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'expired@example.com',
            'expirationDatetime' => (new \DateTime())->modify('-1 day'),
        ])->create();

        // Accepted invitation (should not appear)
        BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'accepted@example.com',
            'status' => InvitationStatus::Accepted,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/invitations');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceInvitation',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/invitations',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/invitations/' . $invitation->id,
                    '@type' => 'BandSpaceInvitation',
                    'id' => $invitation->id,
                    'band_space_id' => $bandSpace->id,
                    'email' => 'pending@example.com',
                    'status' => 'pending',
                    'creation_datetime' => '2026-06-01T12:00:00+00:00',
                    'expiration_datetime' => '2027-06-08T12:00:00+00:00',
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_non_admin_cannot_list_invitations(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/invitations');

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
}
