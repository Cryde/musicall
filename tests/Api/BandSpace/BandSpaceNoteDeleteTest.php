<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\User\UserFactory;
use App\Enum\BandSpace\MembershipStatus;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceNoteDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_delete_note_as_its_author(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'To Delete',
            'createdBy' => $user,
        ])->create();
        $noteId = (string) $note->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $noteId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $this->assertNull($noteRepository->find($noteId));

        \Zenstruck\Foundry\Persistence\refresh($bandSpace);
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
            'createdBy' => $user,
        ])->create();

        $childNote = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Child',
            'parent' => $parentNote,
        ])->create();
        $parentNoteId = (string) $parentNote->id;
        $childId = (string) $childNote->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $parentNoteId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $this->assertNull($noteRepository->find($parentNoteId));
        $this->assertNull($noteRepository->find($childId));
    }

    public function test_delete_note_detaches_its_files_and_leaves_them_deletable(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'title' => 'Répétition', 'createdBy' => $user])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $attachment = BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'note',
            'sourceId' => Uuid::fromString((string) $note->id),
            'attachedBy' => $user,
        ]);
        $noteId = (string) $note->id;
        $fileId = (string) $file->id;
        $attachmentId = (string) $attachment->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $noteId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $attachmentRepository = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);
        $this->assertNull($attachmentRepository->find($attachmentId));

        $fileRepository = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertNotNull($fileRepository->find($fileId));
        $this->assertNull($fileRepository->find($fileId)->archiveDatetime);

        \Zenstruck\Foundry\Persistence\refresh($bandSpace);
        $activityRepository = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepository->findForResource($bandSpace, BandSpaceModule::File, $fileId);
        $this->assertCount(1, $activities);
        $this->assertSame('source_deleted', $activities[0]->type);
        $this->assertSame([
            'source_type' => 'note',
            'source_id' => $noteId,
            'source_label' => 'Répétition',
        ], $activities[0]->payload);

        // The regression this test exists for. BandSpaceFileDeleteProcessor refuses with a 422 while
        // findByFile returns anything, and that used to be permanent: the detach endpoint 404s once
        // the source is gone, so no call could ever release the file. The endpoint is not exercised
        // here because the stateless api firewall allows one authenticated request per test and it
        // went to the deletion above, so the guard's own query stands in for it.
        $this->assertSame([], $attachmentRepository->findByFile($fileRepository->find($fileId)));
    }

    public function test_delete_note_detaches_the_files_of_its_descendants(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $parent = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'title' => 'Tournée', 'createdBy' => $user])->create();
        $child = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'title' => 'Paris', 'parent' => $parent])->create();
        $grandChild = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'title' => 'Backline', 'parent' => $child])->create();

        $childFile = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $grandChildFile = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $childAttachment = BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $childFile,
            'sourceType' => 'note',
            'sourceId' => Uuid::fromString((string) $child->id),
            'attachedBy' => $user,
        ]);
        $grandChildAttachment = BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $grandChildFile,
            'sourceType' => 'note',
            'sourceId' => Uuid::fromString((string) $grandChild->id),
            'attachedBy' => $user,
        ]);

        $childAttachmentId = (string) $childAttachment->id;
        $grandChildAttachmentId = (string) $grandChildAttachment->id;
        $grandChildFileId = (string) $grandChildFile->id;
        $grandChildId = (string) $grandChild->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $parent->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $attachmentRepository = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);
        $this->assertNull($attachmentRepository->find($childAttachmentId));
        $this->assertNull($attachmentRepository->find($grandChildAttachmentId));

        \Zenstruck\Foundry\Persistence\refresh($bandSpace);
        $activityRepository = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepository->findForResource($bandSpace, BandSpaceModule::File, $grandChildFileId);
        $this->assertCount(1, $activities);
        $this->assertSame([
            'source_type' => 'note',
            'source_id' => $grandChildId,
            'source_label' => 'Backline',
        ], $activities[0]->payload);
    }

    public function test_delete_note_written_by_another_member_as_admin(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $author = UserFactory::new()->create(['username' => 'author', 'email' => 'author@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $admin,
            'role' => Role::Admin,
        ])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Écrite par un autre',
            'createdBy' => $author,
        ])->create();
        $noteId = (string) $note->id;

        $this->client->loginUser($admin);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $noteId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNull(self::getContainer()->get(BandSpaceNoteRepository::class)->find($noteId));
    }

    /**
     * The hole this endpoint had. Deletion cascades through band_space_note.parent_id, so a member who
     * wrote none of it could take a whole subtree of somebody else's pages behind one confirm dialog.
     */
    public function test_delete_note_written_by_another_member_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $author = UserFactory::new()->create(['username' => 'author', 'email' => 'author@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Tournée',
            'createdBy' => $author,
        ])->create();
        $child = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Paris',
            'parent' => $note,
            'createdBy' => $author,
        ])->create();
        $noteId = (string) $note->id;
        $childId = (string) $child->id;

        $this->client->loginUser($member);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $noteId);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Seul le créateur ou un administrateur peut supprimer cette note',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Seul le créateur ou un administrateur peut supprimer cette note',
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $this->assertNotNull($noteRepository->find($noteId));
        $this->assertNotNull($noteRepository->find($childId));
    }

    public function test_delete_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

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

        $this->client->loginUser($otherUser);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
        ]);
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

        $this->client->loginUser($inactiveUser);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_delete_unauthenticated(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'title' => 'Note'])->create();

        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
