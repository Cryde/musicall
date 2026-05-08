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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceNoteDeleteTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_delete_note(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'To Delete',
        ])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();
        $note = $note->_real();
        $noteId = (string) $note->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $noteId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $this->assertNull($noteRepository->find($noteId));

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Notes, $noteId);
        $this->assertCount(1, $activities);
        $this->assertSame('note_deleted', $activities[0]->type);
        $this->assertSame(['title' => 'To Delete'], $activities[0]->payload);
    }

    public function test_delete_note_cascades_children(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $parentNote = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Parent',
        ])->create();

        $childNote = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Child',
            'parent' => $parentNote,
        ])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();
        $parentNote = $parentNote->_real();
        $parentNoteId = (string) $parentNote->id;
        $childId = (string) $childNote->_real()->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $parentNoteId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $this->assertNull($noteRepository->find($parentNoteId));
        $this->assertNull($noteRepository->find($childId));
    }

    public function test_delete_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/nonexistent-id');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_delete_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Protected Note',
        ])->create();

        $otherUser = $otherUser->_real();
        $bandSpace = $bandSpace->_real();
        $note = $note->_real();

        $this->client->loginUser($otherUser);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_delete_inactive_member(): void
    {
        $inactiveUser = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $inactiveUser,
            'status' => MembershipStatus::Left,
        ])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Protected Note',
        ])->create();

        $inactiveUser = $inactiveUser->_real();
        $bandSpace = $bandSpace->_real();
        $note = $note->_real();

        $this->client->loginUser($inactiveUser);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_delete_unauthenticated(): void
    {
        $bandSpace = BandSpaceFactory::new()->create()->_real();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'title' => 'Note'])->create()->_real();

        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
