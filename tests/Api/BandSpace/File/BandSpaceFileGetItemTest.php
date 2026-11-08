<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileTagFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceFileGetItemTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_item_returns_full_dto(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Setlists'])->create();
        $tag = BandSpaceFileTagFactory::new(['bandSpace' => $bandSpace, 'name' => 'masters'])->create();

        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'folder' => $folder,
            'originalName' => 'master.flac',
        ])->create();
        $version = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file,
            'versionNumber' => 3,
            'mimeType' => 'audio/flac',
            'size' => 4_096_000,
        ])->create();
        $file->currentVersion = $version;
        $file->tags->add($tag);
        \Zenstruck\Foundry\Persistence\save($file);

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id, [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame('master.flac', $response['original_name']);
        $this->assertSame(4_096_000, $response['size']);
        $this->assertSame('audio/flac', $response['mime_type']);
        $this->assertSame($folder->id, $response['folder_id']);
        $this->assertCount(1, $response['folder_path']);
        $this->assertSame('Setlists', $response['folder_path'][0]['name']);
        $this->assertCount(1, $response['tags']);
        $this->assertSame('masters', $response['tags'][0]['name']);
        $this->assertSame(1, $response['version_count']);
        $this->assertSame(3, $response['current_version_number']);
        $this->assertSame('alice', $response['created_by']['username']);
        $this->assertStringContainsString('/download', $response['download_url']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function chatMessageSourceLabels(): iterable
    {
        yield 'in French' => ['fr', 'Message du chat'];
        yield 'in English' => ['en', 'Chat message'];
    }

    /** The one source label the server writes itself, a chat message having no title, follows the reader's language. */
    #[DataProvider('chatMessageSourceLabels')]
    public function test_get_item_names_a_chat_message_source_in_the_reader_locale(string $locale, string $sourceLabel): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $channel = MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $user,
            'content' => '',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();

        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'photo.webp',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->withAttachment(['type' => 'message', 'id' => (string) $message->id])->create();
        $version = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file,
            'createdBy' => $user,
            'mimeType' => 'image/webp',
            'size' => 2048,
        ])->create();
        $file->currentVersion = $version;
        $message->imageFileId = (string) $file->id;
        \Zenstruck\Foundry\Persistence\save($file);
        \Zenstruck\Foundry\Persistence\save($message);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id,
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_X_LOCALE' => $locale],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFile',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id,
            '@type' => 'BandSpaceFile',
            'id' => $file->id,
            'band_space_id' => $bandSpace->id,
            'original_name' => 'photo.webp',
            'size' => 2048,
            'mime_type' => 'image/webp',
            'folder_id' => null,
            'folder_path' => [],
            'tags' => [],
            'attachments' => [
                ['source_type' => 'message', 'source_id' => $message->id, 'source_label' => $sourceLabel],
            ],
            'current_version_id' => $version->id,
            'current_version_number' => 1,
            'version_count' => 1,
            'created_by' => [
                'id' => $user->id,
                'username' => 'batteur',
                'display_name' => 'batteur',
                'profile_picture_url' => null,
            ],
            'download_url' => '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id . '/download',
            'creation_datetime' => '2026-09-10T20:00:00+00:00',
            'update_datetime' => null,
            'archive_datetime' => null,
            'purge_datetime' => null,
        ]);
    }

    public function test_get_item_not_found_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/files/00000000-0000-0000-0000-000000000000',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
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

    public function test_get_item_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $member])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id, [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Tu n'es pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Tu n'es pas membre de ce Band Space",
        ]);
    }

    public function test_get_item_archived_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'archiveDatetime' => new \DateTimeImmutable('-1 day'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id, [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

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
}
