<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceNoteGetTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_item_with_content(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $content = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]]]];
        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'content' => $content,
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();
        $note = $note->_real();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => $note->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => $content,
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'update_datetime' => null,
        ]);
    }

    public function test_get_item_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes/nonexistent-id');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_get_item_unauthenticated(): void
    {
        $bandSpace = BandSpaceFactory::new()->create()->_real();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'title' => 'Note'])->create()->_real();

        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_get_item_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'title' => 'Secret Note'])->create();

        $otherUser = $otherUser->_real();
        $bandSpace = $bandSpace->_real();
        $note = $note->_real();

        $this->client->loginUser($otherUser);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
