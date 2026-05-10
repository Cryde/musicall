<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileTagFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class BandSpaceFileUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_rename_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'old.pdf',
        ])->create();

        $bandSpaceId = $bandSpace->id;
        $fileId = $file->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpaceId . '/files/' . $fileId,
            ['originalName' => 'new.pdf'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame('new.pdf', $response['original_name']);

        $this->assertActivityRecorded($bandSpace, $fileId, 'renamed', ['from' => 'old.pdf', 'to' => 'new.pdf']);
    }

    public function test_rename_strips_path_separators(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id,
            ['originalName' => '../../etc/passwd.txt'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame('....etcpasswd.txt', $response['original_name']);
    }

    public function test_move_file_to_folder(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Live'])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $bandSpaceId = $bandSpace->id;
        $fileId = $file->id;
        $folderId = $folder->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpaceId . '/files/' . $fileId,
            ['folderId' => $folderId],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame($folderId, $response['folder_id']);

        $this->assertActivityRecorded($bandSpace, $fileId, 'moved', [
            'from_folder_id' => null,
            'to_folder_id' => $folderId,
            'to_folder_name' => 'Live',
        ]);
    }

    public function test_move_file_to_root(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Live'])->create();
        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'folder' => $folder,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id,
            ['folderId' => null],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertNull($response['folder_id']);
    }

    public function test_move_to_folder_in_other_band_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $foreignFolder = BandSpaceFolderFactory::new(['bandSpace' => $otherBand, 'createdBy' => $user, 'name' => 'X'])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id,
            ['folderId' => $foreignFolder->id],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Dossier introuvable dans ce Band Space',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Dossier introuvable dans ce Band Space',
        ]);
    }

    public function test_replace_tags_records_tagged_and_untagged(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $tagKept = BandSpaceFileTagFactory::new(['bandSpace' => $bandSpace, 'name' => 'keep'])->create();
        $tagRemoved = BandSpaceFileTagFactory::new(['bandSpace' => $bandSpace, 'name' => 'remove'])->create();
        $tagAdded = BandSpaceFileTagFactory::new(['bandSpace' => $bandSpace, 'name' => 'add'])->create();

        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'tags' => new ArrayCollection([$tagKept, $tagRemoved]),
        ])->create();

        $bandSpaceId = $bandSpace->id;
        $fileId = $file->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpaceId . '/files/' . $fileId,
            ['tagIds' => [$tagKept->id, $tagAdded->id]],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $tagIdsInResponse = array_map(fn (array $t): string => $t['id'], $response['tags']);
        sort($tagIdsInResponse);
        $expected = [$tagKept->id, $tagAdded->id];
        sort($expected);
        $this->assertSame($expected, $tagIdsInResponse);

        $this->assertActivityRecorded($bandSpace, $fileId, 'untagged', [
            'tag_id' => $tagRemoved->id,
            'tag_name' => 'remove',
        ]);
        $this->assertActivityRecorded($bandSpace, $fileId, 'tagged', [
            'tag_id' => $tagAdded->id,
            'tag_name' => 'add',
        ]);
    }

    public function test_replace_tags_with_unknown_id_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id,
            ['tagIds' => [Uuid::uuid4()->toString()]],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Une ou plusieurs étiquettes sont invalides pour ce Band Space',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Une ou plusieurs étiquettes sont invalides pour ce Band Space',
        ]);
    }

    public function test_set_non_null_attachment_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id,
            ['attachedSourceType' => 'task', 'attachedSourceId' => Uuid::uuid4()->toString()],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "L'attachement à une ressource doit être effectué via les endpoints dédiés (tâche, entrée financière, note)",
            'status' => 422,
            'type' => '/errors/422',
            'description' => "L'attachement à une ressource doit être effectué via les endpoints dédiés (tâche, entrée financière, note)",
        ]);
    }

    public function test_member_who_did_not_create_file_can_edit(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'owner', 'email' => 'owner@test.com']);
        $editor = UserFactory::new()->asBaseUser()->create(['username' => 'editor', 'email' => 'editor@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $editor])->create();

        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $owner,
            'originalName' => 'shared.pdf',
        ])->create();

        $this->client->loginUser($editor);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id,
            ['originalName' => 'edited.pdf'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame('edited.pdf', $response['original_name']);
    }

    public function test_non_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $member])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id,
            ['originalName' => 'hacked.pdf'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

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

    public function test_archived_file_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'archiveDatetime' => new \DateTimeImmutable('2026-01-01'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id,
            ['originalName' => 'whatever.pdf'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Fichier introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Fichier introuvable',
        ]);
    }

    public function test_no_op_patch_does_not_change_update_datetime(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'unchanged.pdf',
        ])->create();
        $fileId = $file->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $fileId,
            ['originalName' => 'unchanged.pdf'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();

        self::getContainer()->get('doctrine')->getManager()->clear();
        /** @var BandSpaceFileRepository $repo */
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloaded = $repo->find($fileId);
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->updateDatetime);
    }

    /**
     * @param array<string, mixed> $expectedPayload
     */
    private function assertActivityRecorded(
        \App\Entity\BandSpace\BandSpace $bandSpace,
        string $resourceId,
        string $type,
        array $expectedPayload,
    ): void {
        /** @var BandSpaceActivityRepository $repo */
        $repo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $repo->findForResource($bandSpace, BandSpaceModule::File, $resourceId);
        $matching = array_values(array_filter(
            $activities,
            fn (\App\Entity\BandSpace\BandSpaceActivity $a): bool => $a->type === $type
                && $a->payload === $expectedPayload,
        ));
        $this->assertCount(1, $matching, sprintf(
            'Expected exactly one "%s" activity with payload %s, got %d activities of type "%s" total.',
            $type,
            json_encode($expectedPayload, JSON_THROW_ON_ERROR),
            count(array_filter($activities, fn (\App\Entity\BandSpace\BandSpaceActivity $a): bool => $a->type === $type)),
            $type,
        ));
    }
}
