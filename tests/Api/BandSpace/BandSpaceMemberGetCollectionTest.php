<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\Role;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceMemberGetCollectionTest extends ApiTestCase
{
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

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/members');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceMember',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/members',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/members/' . $adminMembership->id,
                    '@type' => 'BandSpaceMember',
                    'id' => $adminMembership->id,
                    'band_space_id' => $bandSpace->id,
                    'user_id' => $admin->id,
                    'username' => $admin->username,
                    'role' => 'admin',
                    'profile_picture_url' => null,
                    'creation_datetime' => '2024-01-01T10:00:00+00:00',
                    'status' => 'active',
                    'left_datetime' => null,
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/members/' . $memberMembership->id,
                    '@type' => 'BandSpaceMember',
                    'id' => $memberMembership->id,
                    'band_space_id' => $bandSpace->id,
                    'user_id' => $member->id,
                    'username' => 'member_user',
                    'role' => 'user',
                    'profile_picture_url' => null,
                    'creation_datetime' => '2024-01-02T10:00:00+00:00',
                    'status' => 'active',
                    'left_datetime' => null,
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

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/members');

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

        $this->client->loginUser($outsider);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/members');

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
