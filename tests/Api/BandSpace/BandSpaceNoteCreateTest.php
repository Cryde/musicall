<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceNoteRepository;
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
class BandSpaceNoteCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_create_note(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes',
            ['title' => 'My New Note'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $notes = $noteRepository->findByBandSpace($bandSpace);
        $this->assertCount(1, $notes);

        $note = $notes[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => $note->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'My New Note',
            'parent_id' => null,
            'position' => 0,
            'content' => null,
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $note->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Notes, $note->id);
        $this->assertCount(1, $activities);
        $this->assertSame('note_created', $activities[0]->type);
        $this->assertSame(['title' => 'My New Note'], $activities[0]->payload);
        $this->assertSame($user->id, $activities[0]->actor?->id);
    }

    public function test_create_note_with_parent(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $parentNote = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Parent',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes',
            ['title' => 'Child Note', 'parent_id' => $parentNote->id],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $created = $noteRepository->findOneBy(['title' => 'Child Note']);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $created->id,
            '@type' => 'BandSpaceNote',
            'id' => $created->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Child Note',
            'parent_id' => $parentNote->id,
            'position' => 0,
            'content' => null,
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $created->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    public function test_create_note_auto_position(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Existing Note',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes',
            ['title' => 'New Note'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $created = $noteRepository->findOneBy(['title' => 'New Note']);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $created->id,
            '@type' => 'BandSpaceNote',
            'id' => $created->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'New Note',
            'parent_id' => null,
            'position' => 1,
            'content' => null,
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $created->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    public function test_create_note_validation_empty_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes',
            ['title' => ''],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_create_note_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes',
            ['title' => 'Forbidden Note'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_create_note_inactive_member(): void
    {
        $inactiveUser = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $inactiveUser,
            'status' => MembershipStatus::Left,
        ])->create();

        $this->client->loginUser($inactiveUser);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes',
            ['title' => 'Forbidden Note'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_create_note_with_parent_from_other_band_space(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace1 = BandSpaceFactory::new()->create();
        $bandSpace2 = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace1, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace2, 'user' => $user])->create();

        $noteInOtherSpace = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace2,
            'title' => 'Note in other space',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace1->id . '/notes',
            ['title' => 'Child', 'parent_id' => (string) $noteInOtherSpace->id],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_create_note_with_invalid_parent_id(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes',
            ['title' => 'Child', 'parent_id' => 'not-a-uuid'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_create_note_exceeds_max_depth(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // Level 1
        $level1 = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Level 1',
            'position' => 0,
        ])->create();

        // Level 2
        $level2 = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Level 2',
            'parent' => $level1,
            'position' => 0,
        ])->create();

        // Level 3
        $level3 = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Level 3',
            'parent' => $level2,
            'position' => 0,
        ])->create();

        // Try to create level 4 — should fail
        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes',
            ['title' => 'Level 4', 'parent_id' => (string) $level3->id],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_a3b2c1d0-4e5f-6a7b-8c9d-0e1f2a3b4c5d',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'parent_id',
                    'message' => 'La profondeur maximale de 3 niveaux est atteinte',
                    'code' => 'music_all_a3b2c1d0-4e5f-6a7b-8c9d-0e1f2a3b4c5d',
                ],
            ],
            'detail' => 'parent_id: La profondeur maximale de 3 niveaux est atteinte',
            'description' => 'parent_id: La profondeur maximale de 3 niveaux est atteinte',
            'type' => '/validation_errors/music_all_a3b2c1d0-4e5f-6a7b-8c9d-0e1f2a3b4c5d',
            'title' => 'An error occurred',
        ]);
    }

    public function test_create_note_at_max_depth_is_allowed(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // Level 1
        $level1 = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Level 1',
            'position' => 0,
        ])->create();

        // Level 2
        $level2 = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Level 2',
            'parent' => $level1,
            'position' => 0,
        ])->create();

        // Create level 3 under level 2 — should succeed (depth = 3 is the max allowed)
        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes',
            ['title' => 'Level 3', 'parent_id' => (string) $level2->id],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function test_create_note_unauthenticated(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();

        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
