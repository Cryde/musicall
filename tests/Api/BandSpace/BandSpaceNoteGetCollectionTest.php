<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use App\Tests\Factory\User\UserFactory;
use App\Enum\BandSpace\MembershipStatus;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class BandSpaceNoteGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_collection(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note1 = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'First Note',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
        $note2 = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Second Note',
            'position' => 1,
            'creationDatetime' => new \DateTime('2024-01-02 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note1->id,
                    '@type' => 'BandSpaceNote',
                    'id' => $note1->id,
                    'band_space_id' => $bandSpace->id,
                    'title' => 'First Note',
                    'parent_id' => null,
                    'position' => 0,
                    'content' => null,
                    'has_children' => false,
                    'emoji' => null,
                    'creation_datetime' => '2024-01-01T10:00:00+00:00',
                    'update_datetime' => null,
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note2->id,
                    '@type' => 'BandSpaceNote',
                    'id' => $note2->id,
                    'band_space_id' => $bandSpace->id,
                    'title' => 'Second Note',
                    'parent_id' => null,
                    'position' => 1,
                    'content' => null,
                    'has_children' => false,
                    'emoji' => null,
                    'creation_datetime' => '2024-01-02T10:00:00+00:00',
                    'update_datetime' => null,
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_get_collection_empty(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes',
            '@type' => 'Collection',
            'member' => [],
            'totalItems' => 0,
        ]);
    }

    public function test_get_collection_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_collection_inactive_member(): void
    {
        $inactiveUser = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $inactiveUser,
            'status' => MembershipStatus::Left,
        ])->create();

        $this->client->loginUser($inactiveUser);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_collection_unauthenticated(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();

        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_get_collection_does_not_include_content(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Note with content',
            'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
                    '@type' => 'BandSpaceNote',
                    'id' => $note->id,
                    'band_space_id' => $bandSpace->id,
                    'title' => 'Note with content',
                    'parent_id' => null,
                    'position' => 0,
                    'content' => null,
                    'has_children' => false,
                    'emoji' => null,
                    'creation_datetime' => '2024-01-01T10:00:00+00:00',
                    'update_datetime' => null,
                ],
            ],
            'totalItems' => 1,
        ]);
    }
}
