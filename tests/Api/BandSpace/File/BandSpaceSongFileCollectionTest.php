<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BandSpaceSongFileCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_list_returns_only_files_attached_to_song(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Mine'])->create();
        $otherSong = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Other'])->create();

        $attachedFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'tab.pdf',
        ])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $attachedFile,
            'sourceType' => 'song',
            'sourceId' => Uuid::fromString((string) $song->id),
            'attachedBy' => $user,
        ]);

        // Same source type, different song — must not show up.
        $otherFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'on-other-song.pdf',
        ])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $otherFile,
            'sourceType' => 'song',
            'sourceId' => Uuid::fromString((string) $otherSong->id),
            'attachedBy' => $user,
        ]);

        // Manual file (no attachment) — must not show up.
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'manual.pdf',
        ])->create();

        // Archived attachment — must not show up.
        $archivedFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'archived.pdf',
            'archiveDatetime' => new \DateTimeImmutable(),
        ])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $archivedFile,
            'sourceType' => 'song',
            'sourceId' => Uuid::fromString((string) $song->id),
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertCount(1, $response['member']);
        $this->assertSame('tab.pdf', $response['member'][0]['original_name']);
        $this->assertCount(1, $response['member'][0]['attachments']);
        $this->assertSame('song', $response['member'][0]['attachments'][0]['source_type']);
        $this->assertSame((string) $song->id, $response['member'][0]['attachments'][0]['source_id']);
    }

    public function test_list_unknown_song_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/songs/00000000-0000-0000-0000-000000000000/files',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Chanson introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Chanson introuvable',
        ]);
    }

    public function test_list_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files',
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
    }
}
