<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileTagFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class BandSpaceFileCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_list_returns_files_in_band(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'rider.pdf'])->create();
        $version = BandSpaceFileVersionFactory::new(['bandSpaceFile' => $file, 'mimeType' => 'application/pdf'])->create();
        $file->currentVersion = $version;
        \Zenstruck\Foundry\Persistence\save($file);

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertCount(1, $response['member']);
        $this->assertSame('rider.pdf', $response['member'][0]['original_name']);
    }

    public function test_list_filtered_by_folder(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Setlists'])->create();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'folder' => $folder, 'originalName' => 'in-folder.pdf'])->create();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'in-root.pdf'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?folder_id=' . $folder->id, [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('in-folder.pdf', $response['member'][0]['original_name']);
    }

    public function test_list_filtered_by_tag(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $tag = BandSpaceFileTagFactory::new(['bandSpace' => $bandSpace, 'name' => 'masters'])->create();
        $tagged = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'tagged.pdf'])->create();
        $tagged->tags->add($tag);
        \Zenstruck\Foundry\Persistence\save($tagged);

        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'untagged.pdf'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?tag_id=' . $tag->id, [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('tagged.pdf', $response['member'][0]['original_name']);
    }

    public function test_list_filtered_by_finance_entry_id(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $entryId = '11111111-1111-1111-1111-111111111111';
        $otherEntryId = '22222222-2222-2222-2222-222222222222';

        $matchingFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'matching.pdf',
        ])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $matchingFile,
            'sourceType' => 'finance',
            'sourceId' => $entryId,
            'attachedBy' => $user,
        ]);
        $otherFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'other-entry.pdf',
        ])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $otherFile,
            'sourceType' => 'finance',
            'sourceId' => $otherEntryId,
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/files?source=finance&finance_entry_id=' . $entryId,
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('matching.pdf', $response['member'][0]['original_name']);
    }

    public function test_list_filtered_by_source_manual_excludes_attached(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'standalone.pdf'])->create();
        $attachedFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'attached-to-task.pdf',
        ])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $attachedFile,
            'sourceType' => 'task',
            'sourceId' => \Ramsey\Uuid\Uuid::fromString('7e57d004-2b97-0e7a-b45f-5387367791cd'),
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?source=manual', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('standalone.pdf', $response['member'][0]['original_name']);
    }

    public function test_list_filtered_by_query_substring(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'master-2025.flac'])->create();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'rider.pdf'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?query=master', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('master-2025.flac', $response['member'][0]['original_name']);
    }

    public function test_list_filtered_by_mime_prefix(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $audio = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'master.flac'])->create();
        $audioVersion = BandSpaceFileVersionFactory::new(['bandSpaceFile' => $audio, 'mimeType' => 'audio/flac'])->create();
        $audio->currentVersion = $audioVersion;
        \Zenstruck\Foundry\Persistence\save($audio);

        $pdf = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'rider.pdf'])->create();
        $pdfVersion = BandSpaceFileVersionFactory::new(['bandSpaceFile' => $pdf, 'mimeType' => 'application/pdf'])->create();
        $pdf->currentVersion = $pdfVersion;
        \Zenstruck\Foundry\Persistence\save($pdf);

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?mime=audio/', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('master.flac', $response['member'][0]['original_name']);
    }

    public function test_list_filtered_by_uploader(): void
    {
        $alice = UserFactory::new()->asBaseUser()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bob = UserFactory::new()->asBaseUser()->create(['username' => 'bob', 'email' => 'bob@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $alice])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $bob])->create();

        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $alice, 'originalName' => 'alice.pdf'])->create();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $bob, 'originalName' => 'bob.pdf'])->create();

        $this->client->loginUser($alice);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?uploader_id=' . $bob->id, [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('bob.pdf', $response['member'][0]['original_name']);
    }

    public function test_list_paginated(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        for ($i = 0; $i < 60; $i++) {
            BandSpaceFileFactory::new([
                'bandSpace' => $bandSpace,
                'createdBy' => $user,
                'originalName' => sprintf('file-%02d.pdf', $i),
            ])->create();
        }

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files?page=2', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(60, $response['totalItems']);
        $this->assertCount(10, $response['member']);
    }

    public function test_list_excludes_archived(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'live.pdf'])->create();
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'archived.pdf',
            'archiveDatetime' => new \DateTimeImmutable('-1 day'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('live.pdf', $response['member'][0]['original_name']);
    }

    public function test_list_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

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
}
