<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceMemberDeleteTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_kick_member(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $memberMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($admin->_real());
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/members/' . $memberMembership->_real()->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $membershipRepository = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertFalse($membershipRepository->isMember($bandSpace->_real(), $member->_real()));

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace->_real(), BandSpaceModule::Settings, $member->_real()->id);
        $this->assertCount(1, $activities);
        $this->assertSame('member_removed', $activities[0]->type);
        $this->assertSame(
            ['target_user_id' => $member->_real()->id, 'target_username' => 'member_user'],
            $activities[0]->payload,
        );
        $this->assertSame($admin->_real()->id, $activities[0]->actor?->id);
    }

    public function test_cannot_kick_self(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();

        $adminMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin->_real());
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/members/' . $adminMembership->_real()->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous ne pouvez pas vous exclure vous-même. Utilisez la fonction "Quitter"',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Vous ne pouvez pas vous exclure vous-même. Utilisez la fonction "Quitter"',
        ]);
    }

    public function test_non_admin_cannot_kick(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        $adminMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($member->_real());
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/members/' . $adminMembership->_real()->id
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

    public function test_kick_member_from_other_band_space(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace1 = BandSpaceFactory::new()->create();
        $bandSpace2 = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace1, 'user' => $admin, 'role' => Role::Admin])->create();
        $memberMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace2, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($admin->_real());
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace1->_real()->id . '/members/' . $memberMembership->_real()->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Membre introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Membre introuvable',
        ]);
    }
}
