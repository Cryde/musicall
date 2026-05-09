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
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceInvitationInfoTest extends ApiTestCase
{
    use ResetDatabase, Factories;
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
    }

    public function test_get_invitation_info_already_accepted(): void
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
    }
}
