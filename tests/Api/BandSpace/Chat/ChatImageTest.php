<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\Message\MessageRepository;
use App\Service\BandSpace\Chat\ChatImageConverter;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * An image in the chat (#973): stored as a band space file under the `message` source, converted to
 * WebP, streamed inline, and gone with its message.
 */
#[ResetDatabase]
class ChatImageTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string STORED_BYTES = 'webp-bytes';

    /** @var string[] */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->temporaryFiles = [];

        parent::tearDown();
    }

    public function test_post_an_image_alone(): void
    {
        $this->client->disableReboot();
        [$member, $space, $channel] = $this->memberWithChannel();

        $this->client->loginUser($member);
        $this->postImage($space, $this->jpegUpload(3200, 2000, 'IMG_1234.jpg'));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]);
        $this->assertInstanceOf(Message::class, $message);
        $file = self::getContainer()->get(BandSpaceFileRepository::class)->findOneBy(['bandSpace' => $space->id]);
        $this->assertInstanceOf(BandSpaceFile::class, $file);

        $this->assertJsonEquals($this->expectedMessage($message, $space, $member, '', '', [
            'file_id' => (string) $file->id,
            'is_available' => true,
        ]));

        $this->assertSame((string) $file->id, $message->imageFileId);
        $this->assertSame('IMG_1234.webp', $file->originalName);
        $this->assertNull($file->folder, 'No folder: the « Chat » virtual folder lists it instead');
        $this->assertNotNull($file->currentVersion);
        $this->assertSame('image/webp', $file->currentVersion->mimeType);
        // The bytes really reached storage, and they are the converted WebP, not the JPEG that was sent.
        $this->assertNotEmpty($file->currentVersion->storagePath);
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $stored = $filesystem->read('/band_space_files/' . $space->id . '/' . $file->currentVersion->storagePath);
        $this->assertSame($file->currentVersion->size, strlen($stored));
        $this->assertSame([1600, 1000, IMAGETYPE_WEBP], array_slice((array) getimagesizefromstring($stored), 0, 3));

        $attachments = self::getContainer()->get(BandSpaceFileAttachmentRepository::class)->findByFile($file);
        $this->assertCount(1, $attachments);
        $this->assertSame('message', $attachments[0]->sourceType);
        $this->assertSame((string) $message->id, $attachments[0]->sourceId);
    }

    public function test_post_an_image_with_a_caption(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();

        $this->client->loginUser($member);
        $this->postImage($space, $this->jpegUpload(800, 600, 'setlist.jpg'), 'la setlist de ce soir');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]);
        $this->assertInstanceOf(Message::class, $message);
        $this->assertJsonEquals($this->expectedMessage($message, $space, $member, 'la setlist de ce soir', 'la setlist de ce soir', [
            'file_id' => (string) $message->imageFileId,
            'is_available' => true,
        ]));
    }

    /**
     * The multipart decoder JSON decodes its fields, which a caption must escape: this is what a
     * member typed, and decoding it would have refused the message with a type error.
     */
    public function test_a_caption_that_looks_like_json_is_kept_as_text(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();

        $this->client->loginUser($member);
        $this->postImage($space, $this->jpegUpload(800, 600, 'setlist.jpg'), '["intro","couplet"]');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]);
        $this->assertInstanceOf(Message::class, $message);
        $this->assertJsonEquals($this->expectedMessage($message, $space, $member, '[&#34;intro&#34;,&#34;couplet&#34;]', '["intro","couplet"]', [
            'file_id' => (string) $message->imageFileId,
            'is_available' => true,
        ]));
    }

    public function test_an_image_and_attachments_go_in_one_message(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        $task = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Imprimer la setlist']);

        $this->client->loginUser($member);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $space->id . '/chat/messages',
            ['content' => '', 'attachments' => ['task-' . $task->id]],
            ['image' => $this->jpegUpload(800, 600, 'setlist.jpg')],
            ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]);
        $this->assertInstanceOf(Message::class, $message);
        $expected = $this->expectedMessage($message, $space, $member, '', '', [
            'file_id' => (string) $message->imageFileId,
            'is_available' => true,
        ]);
        $expected['attachments'] = [
            ['type' => 'task', 'target_id' => (string) $task->id, 'label' => 'Imprimer la setlist', 'is_available' => true],
        ];
        $this->assertJsonEquals($expected);
    }

    /** Re-encoding through GD would keep only the first frame, so a GIF is stored as it came. */
    public function test_a_gif_is_stored_as_it_came(): void
    {
        [$member, $space] = $this->memberWithChannel();
        $path = $this->temporaryPath();
        imagegif(imagecreatetruecolor(2400, 2400), $path);

        $this->client->loginUser($member);
        $this->postImage($space, new UploadedFile($path, 'bravo.gif', 'image/gif', null, true));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $file = self::getContainer()->get(BandSpaceFileRepository::class)->findOneBy(['bandSpace' => $space->id]);
        $this->assertInstanceOf(BandSpaceFile::class, $file);
        $this->assertSame('bravo.gif', $file->originalName);
        $this->assertNotNull($file->currentVersion);
        $this->assertSame('image/gif', $file->currentVersion->mimeType);
        $this->assertSame(filesize($path), $file->currentVersion->size);
    }

    /** A member of one space asking for another space's image through their own space's URL. */
    public function test_an_image_of_another_space_is_not_found(): void
    {
        [$member, $space] = $this->memberWithChannel();
        $otherSpace = BandSpaceFactory::new()->create();
        $otherAuthor = UserFactory::new()->asBaseUser()->create(['username' => 'autre', 'email' => 'autre@test.com']);
        BandSpaceMembershipFactory::new(['bandSpace' => $otherSpace, 'user' => $otherAuthor])->create();
        [$otherFile] = $this->seedChatImage($otherSpace, MessageThreadFactory::new()->forBandSpace($otherSpace)->create(), $otherAuthor);

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/images/' . $otherFile->id, [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Image introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Image introuvable',
        ]);
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        $path = $this->temporaryPath();
        file_put_contents($path, 'pas une image');

        $this->client->loginUser($member);
        $this->postImage($space, new UploadedFile($path, 'photo.png', 'image/png', null, true));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/744f00bc-4389-4c74-92de-9a43cde55534',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'image',
                    'message' => 'Format d\'image non pris en charge (formats acceptés : JPEG, PNG, WebP, GIF)',
                    'code' => '744f00bc-4389-4c74-92de-9a43cde55534',
                ],
            ],
            'detail' => 'image: Format d\'image non pris en charge (formats acceptés : JPEG, PNG, WebP, GIF)',
            'type' => '/validation_errors/744f00bc-4389-4c74-92de-9a43cde55534',
            'title' => 'An error occurred',
            'description' => 'image: Format d\'image non pris en charge (formats acceptés : JPEG, PNG, WebP, GIF)',
        ]);
        $this->assertNull(self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]));
    }

    public function test_a_full_quota_refuses_the_whole_message(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        $space->quotaBytesOverride = 10;
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $upload = $this->jpegUpload(16, 16, 'photo.jpg');
        // What the converter makes of this exact input, so the sentence can be asserted verbatim: a
        // 16 px square stays well under a kilobyte, so it reads in plain bytes.
        $converted = (new ChatImageConverter())->convert(new File($upload->getPathname()), 'image/jpeg');
        $convertedSize = $converted->file->getSize();
        unlink($converted->file->getPathname());
        $detail = sprintf('Quota de stockage dépassé : 0 o utilisés sur 10 o autorisés, il manque %d o pour ajouter %d o.', $convertedSize - 10, $convertedSize);

        $this->client->loginUser($member);
        $this->postImage($space, $upload, 'regardez');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => 422,
            'type' => '/errors/422',
            'description' => $detail,
        ]);
        $this->assertNull(self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]));
        $this->assertNull(self::getContainer()->get(BandSpaceFileRepository::class)->findOneBy(['bandSpace' => $space->id]));
    }

    public function test_the_image_is_streamed_inline(): void
    {
        $this->client->disableReboot();
        [$member, $space, $channel] = $this->memberWithChannel();
        [$file] = $this->seedChatImage($space, $channel, $member);

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/images/' . $file->id);

        $this->assertResponseIsSuccessful();
        $headers = $this->client->getResponse()->headers;
        $this->assertSame('image/webp', $headers->get('Content-Type'));
        $this->assertSame('inline; filename=photo.webp', $headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        $this->assertSame('max-age=86400, private', $headers->get('Cache-Control'));
        $this->assertSame(self::STORED_BYTES, $this->client->getInternalResponse()->getContent());
    }

    public function test_a_file_the_chat_did_not_store_is_not_streamed_inline(): void
    {
        [$member, $space] = $this->memberWithChannel();
        $file = BandSpaceFileFactory::new(['bandSpace' => $space, 'createdBy' => $member, 'originalName' => 'affiche.png'])->create();
        $version = BandSpaceFileVersionFactory::new(['bandSpaceFile' => $file, 'createdBy' => $member, 'mimeType' => 'image/png'])->create();
        $file->currentVersion = $version;
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/images/' . $file->id, [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Image introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Image introuvable',
        ]);
    }

    public function test_a_non_member_cannot_see_the_image(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        [$file] = $this->seedChatImage($space, $channel, $member);
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);

        $this->client->loginUser($outsider);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/images/' . $file->id, [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous n\'êtes pas membre de ce Band Space',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous n\'êtes pas membre de ce Band Space',
        ]);
    }

    public function test_deleting_the_message_deletes_its_image_for_good(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        [$file, $message] = $this->seedChatImage($space, $channel, $member);
        $fileId = (string) $file->id;

        $this->client->loginUser($member);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $space->id . '/chat/messages/' . $message->id, [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertNull(self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId), 'Purged, not merely trashed');
        $tombstone = self::getContainer()->get(MessageRepository::class)->find((string) $message->id);
        $this->assertInstanceOf(Message::class, $tombstone);
        $this->assertNull($tombstone->imageFileId);
    }

    public function test_a_chat_image_can_be_deleted_from_files(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        [$file] = $this->seedChatImage($space, $channel, $member);

        $this->client->loginUser($member);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $space->id . '/files/' . $file->id, [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $trashed = self::getContainer()->get(BandSpaceFileRepository::class)->find((string) $file->id);
        $this->assertInstanceOf(BandSpaceFile::class, $trashed);
        $this->assertNotNull($trashed->archiveDatetime);
    }

    public function test_an_image_deleted_from_files_reads_as_unavailable(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        [$file, $message] = $this->seedChatImage($space, $channel, $member);
        $file->archiveDatetime = new \DateTimeImmutable('2026-09-20 10:00:00');
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->loginUser($member);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $space->id . '/chat/messages', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/messages',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $member, '', '', [
                    'file_id' => (string) $file->id,
                    'is_available' => false,
                ], withContext: false),
            ],
        ]);
    }

    public function test_the_chat_virtual_folder_counts_the_chat_images(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        $this->seedChatImage($space, $channel, $member);

        $this->client->loginUser($member);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $space->id . '/folders', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFolder',
            '@id' => '/api/band_spaces/' . $space->id . '/folders',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
            'virtualFolders' => [
                ['id' => 'virtual:task', 'name' => 'Tâches', 'source' => 'task', 'file_count' => 0],
                ['id' => 'virtual:finance', 'name' => 'Finances', 'source' => 'finance', 'file_count' => 0],
                ['id' => 'virtual:note', 'name' => 'Notes', 'source' => 'note', 'file_count' => 0],
                ['id' => 'virtual:song', 'name' => 'Chansons', 'source' => 'song', 'file_count' => 0],
                ['id' => 'virtual:setlist', 'name' => 'Setlists', 'source' => 'setlist', 'file_count' => 0],
                ['id' => 'virtual:message', 'name' => 'Chat', 'source' => 'message', 'file_count' => 1],
            ],
        ]);
    }

    /**
     * @return array{User, BandSpace, MessageThread}
     */
    private function memberWithChannel(): array
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();

        return [$member, $space, MessageThreadFactory::new()->forBandSpace($space)->create()];
    }

    /**
     * What ChatImageStore leaves behind, seeded directly: the file, its version, its `message`
     * attachment, the message pointing back at it, and the bytes in storage.
     *
     * @return array{BandSpaceFile, Message}
     */
    private function seedChatImage(BandSpace $space, MessageThread $channel, User $author): array
    {
        $file = BandSpaceFileFactory::new(['bandSpace' => $space, 'createdBy' => $author, 'originalName' => 'photo.webp'])->create();
        $storagePath = 'chat-' . bin2hex(random_bytes(4)) . '.webp';
        $version = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file,
            'createdBy' => $author,
            'mimeType' => 'image/webp',
            'size' => strlen(self::STORED_BYTES),
            'storagePath' => $storagePath,
        ])->create();
        $file->currentVersion = $version;

        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $author,
            'content' => '',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        $message->imageFileId = (string) $file->id;
        BandSpaceFileAttachmentFactory::new([
            'bandSpaceFile' => $file,
            'sourceType' => 'message',
            'sourceId' => (string) $message->id,
            'attachedBy' => $author,
        ])->create();
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $filesystem->write('/band_space_files/' . $space->id . '/' . $storagePath, self::STORED_BYTES);

        return [$file, $message];
    }

    /**
     * @param array{file_id: string, is_available: bool} $image
     *
     * @return array<string, mixed>
     */
    private function expectedMessage(
        Message $message,
        BandSpace $space,
        User $author,
        string $content,
        string $editableContent,
        array $image,
        bool $withContext = true,
    ): array {
        $expected = [
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $author->id,
            'author_username' => $author->username,
            'author_profile_picture_url' => null,
            'content' => $content,
            'creation_datetime' => $message->creationDatetime->format('c'),
            'reactions' => [],
            'attachments' => [],
            'update_datetime' => null,
            'editable_content' => $editableContent,
            'is_deleted' => false,
            'is_pinned' => false,
            'pinned_datetime' => null,
            'pinned_by_username' => null,
            'read_by_usernames' => [],
            'read_count' => 0,
            'image' => $image,
        ];

        return $withContext ? ['@context' => '/api/contexts/ChatMessage'] + $expected : $expected;
    }

    private function postImage(BandSpace $space, UploadedFile $image, ?string $content = null): void
    {
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $space->id . '/chat/messages',
            $content !== null ? ['content' => $content] : [],
            ['image' => $image],
            ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json'],
        );
    }

    private function jpegUpload(int $width, int $height, string $clientName): UploadedFile
    {
        $path = $this->temporaryPath();
        imagejpeg(imagecreatetruecolor($width, $height), $path);

        return new UploadedFile($path, $clientName, 'image/jpeg', null, true);
    }

    private function temporaryPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'chat_image_api_test_');
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
