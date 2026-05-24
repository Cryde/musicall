<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\User\UserFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BandSpaceSetlistFileCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_list_returns_only_files_attached_to_setlist(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Live'])->create();
        $otherSetlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rehearsal'])->create();

        $attachedFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'cue-sheet.pdf',
        ])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $attachedFile,
            'sourceType' => 'setlist',
            'sourceId' => Uuid::fromString((string) $setlist->id),
            'attachedBy' => $user,
        ]);

        $otherFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'on-other-setlist.pdf',
        ])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $otherFile,
            'sourceType' => 'setlist',
            'sourceId' => Uuid::fromString((string) $otherSetlist->id),
            'attachedBy' => $user,
        ]);

        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'manual.pdf',
        ])->create();

        $archivedFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'archived.pdf',
            'archiveDatetime' => new \DateTimeImmutable(),
        ])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $archivedFile,
            'sourceType' => 'setlist',
            'sourceId' => Uuid::fromString((string) $setlist->id),
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/files',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertCount(1, $response['member']);
        $this->assertSame('cue-sheet.pdf', $response['member'][0]['original_name']);
        $this->assertCount(1, $response['member'][0]['attachments']);
        $this->assertSame('setlist', $response['member'][0]['attachments'][0]['source_type']);
        $this->assertSame((string) $setlist->id, $response['member'][0]['attachments'][0]['source_id']);
    }

    public function test_list_unknown_setlist_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/00000000-0000-0000-0000-000000000000/files',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Setlist introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Setlist introuvable',
        ]);
    }

    public function test_list_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/files',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
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
    }}
