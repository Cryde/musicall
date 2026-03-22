<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\Role;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceMemberGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_list_members(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();

        $adminMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $admin,
            'role' => Role::Admin,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
        $memberMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $member,
            'role' => Role::User,
            'creationDatetime' => new \DateTime('2024-01-02 10:00:00'),
        ])->create();

        $this->client->loginUser($admin->_real());
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->_real()->id . '/members');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceMember',
            '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/members',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/members/' . $adminMembership->_real()->id,
                    '@type' => 'BandSpaceMember',
                    'id' => $adminMembership->_real()->id,
                    'band_space_id' => $bandSpace->_real()->id,
                    'user_id' => $admin->_real()->id,
                    'username' => $admin->_real()->username,
                    'role' => 'admin',
                    'creation_datetime' => '2024-01-01T10:00:00+00:00',
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/members/' . $memberMembership->_real()->id,
                    '@type' => 'BandSpaceMember',
                    'id' => $memberMembership->_real()->id,
                    'band_space_id' => $bandSpace->_real()->id,
                    'user_id' => $member->_real()->id,
                    'username' => 'member_user',
                    'role' => 'user',
                    'creation_datetime' => '2024-01-02T10:00:00+00:00',
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_non_admin_cannot_list_members(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($member->_real());
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->_real()->id . '/members');

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

    public function test_non_member_cannot_list_members(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($outsider->_real());
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->_real()->id . '/members');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous n\'êtes pas membre de ce Band Space',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous n\'êtes pas membre de ce Band Space',
        ]);
    }
}
