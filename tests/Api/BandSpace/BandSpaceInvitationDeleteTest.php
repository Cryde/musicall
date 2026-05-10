<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\InvitationStatus;
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

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class BandSpaceInvitationDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_cancel_invitation(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invited@example.com',
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/invitations/' . $invitation->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $invitationRepo = self::getContainer()->get(BandSpaceInvitationRepository::class);
        $updated = $invitationRepo->find($invitation->id);
        $this->assertSame(InvitationStatus::Expired, $updated->status);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Settings, $invitation->id);
        $this->assertCount(1, $activities);
        $this->assertSame('invitation_revoked', $activities[0]->type);
        $this->assertSame(['email' => 'invited@example.com'], $activities[0]->payload);
        $this->assertSame($admin->id, $activities[0]->actor?->id);
    }

    public function test_non_admin_cannot_cancel_invitation(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invited@example.com',
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($member);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/invitations/' . $invitation->id
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

    public function test_cancel_invitation_from_other_band_space(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace1 = BandSpaceFactory::new()->create();
        $bandSpace2 = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace1, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace2,
            'invitedBy' => $admin,
            'email' => 'invited@example.com',
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace1->id . '/invitations/' . $invitation->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Invitation introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Invitation introuvable',
        ]);
    }
}
