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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A voice note in the chat (#974): converted to AAC by ffmpeg, measured by ffprobe, stored like a
 * chat image under the `message` source, streamed inline, and gone with its message.
 */
#[ResetDatabase]
class ChatVoiceNoteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string FIXTURES = __DIR__ . '/../../../fixtures/voice_notes';

    private const string STORED_BYTES = 'aac-bytes';

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

    public function test_post_a_voice_note(): void
    {
        $this->client->disableReboot();
        [$member, $space, $channel] = $this->memberWithChannel();

        $this->client->loginUser($member);
        $this->postVoiceNote($space, $this->recording('chrome-opus.webm', 'audio/webm'));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]);
        $this->assertInstanceOf(Message::class, $message);
        $file = self::getContainer()->get(BandSpaceFileRepository::class)->findOneBy(['bandSpace' => $space->id]);
        $this->assertInstanceOf(BandSpaceFile::class, $file);

        $this->assertJsonEquals($this->expectedMessage($message, $space, $member, [
            'file_id' => (string) $file->id,
            'duration_seconds' => 2,
            'is_available' => true,
        ]));

        $this->assertSame((string) $file->id, $message->voiceNoteFileId);
        $this->assertSame(2, $message->voiceNoteDurationSeconds);
        $this->assertMatchesRegularExpression('/^note-vocale-\d{4}-\d{2}-\d{2}-\d{6}\.m4a$/', $file->originalName);
        $this->assertNull($file->folder, 'No folder: the « Chat » virtual folder lists it instead');
        $this->assertNotNull($file->currentVersion);
        $this->assertSame('audio/mp4', $file->currentVersion->mimeType);

        // The bytes reached storage, and they are the AAC conversion, not the WebM that was sent.
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $stored = $filesystem->read('/band_space_files/' . $space->id . '/' . $file->currentVersion->storagePath);
        $this->assertSame($file->currentVersion->size, strlen($stored));
        $this->assertSame('ftyp', substr($stored, 4, 4), 'An MP4 container');

        $attachments = self::getContainer()->get(BandSpaceFileAttachmentRepository::class)->findByFile($file);
        $this->assertCount(1, $attachments);
        $this->assertSame('message', $attachments[0]->sourceType);
        $this->assertSame((string) $message->id, $attachments[0]->sourceId);
    }

    public function test_a_voice_note_with_text_is_refused(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();

        $this->client->loginUser($member);
        $this->postVoiceNote($space, $this->recording('chrome-opus.webm', 'audio/webm'), 'écoutez ça');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/6b3befbc-2f01-4ddf-be21-b57898905284',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'voice_note',
                    'message' => 'Une note vocale s\'envoie seule, sans texte, image ni pièce jointe',
                    'code' => '6b3befbc-2f01-4ddf-be21-b57898905284',
                ],
            ],
            'detail' => 'voice_note: Une note vocale s\'envoie seule, sans texte, image ni pièce jointe',
            'type' => '/validation_errors/6b3befbc-2f01-4ddf-be21-b57898905284',
            'title' => 'An error occurred',
            'description' => 'voice_note: Une note vocale s\'envoie seule, sans texte, image ni pièce jointe',
        ]);
        $this->assertNull(self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]));
    }

    public function test_a_voice_note_with_an_image_is_refused(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        $imagePath = $this->temporaryPath();
        imagejpeg(imagecreatetruecolor(100, 100), $imagePath);

        $this->client->loginUser($member);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $space->id . '/chat/messages',
            [],
            [
                'voice_note' => $this->recording('chrome-opus.webm', 'audio/webm'),
                'image' => new UploadedFile($imagePath, 'photo.jpg', 'image/jpeg', null, true),
            ],
            ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/6b3befbc-2f01-4ddf-be21-b57898905284',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'voice_note',
                    'message' => 'Une note vocale s\'envoie seule, sans texte, image ni pièce jointe',
                    'code' => '6b3befbc-2f01-4ddf-be21-b57898905284',
                ],
            ],
            'detail' => 'voice_note: Une note vocale s\'envoie seule, sans texte, image ni pièce jointe',
            'type' => '/validation_errors/6b3befbc-2f01-4ddf-be21-b57898905284',
            'title' => 'An error occurred',
            'description' => 'voice_note: Une note vocale s\'envoie seule, sans texte, image ni pièce jointe',
        ]);
        $this->assertNull(self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]));
    }

    public function test_a_voice_note_with_an_attachment_is_refused(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        $task = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Imprimer la setlist']);

        $this->client->loginUser($member);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $space->id . '/chat/messages',
            ['attachments' => ['task-' . $task->id]],
            ['voice_note' => $this->recording('chrome-opus.webm', 'audio/webm')],
            ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/6b3befbc-2f01-4ddf-be21-b57898905284',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'voice_note',
                    'message' => 'Une note vocale s\'envoie seule, sans texte, image ni pièce jointe',
                    'code' => '6b3befbc-2f01-4ddf-be21-b57898905284',
                ],
            ],
            'detail' => 'voice_note: Une note vocale s\'envoie seule, sans texte, image ni pièce jointe',
            'type' => '/validation_errors/6b3befbc-2f01-4ddf-be21-b57898905284',
            'title' => 'An error occurred',
            'description' => 'voice_note: Une note vocale s\'envoie seule, sans texte, image ni pièce jointe',
        ]);
        $this->assertNull(self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]));
    }

    public function test_a_file_that_is_not_a_recording_is_refused(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        $path = $this->temporaryPath();
        file_put_contents($path, 'pas un enregistrement');

        $this->client->loginUser($member);
        $this->postVoiceNote($space, new UploadedFile($path, 'note.webm', 'audio/webm', null, true));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/744f00bc-4389-4c74-92de-9a43cde55534',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'voice_note',
                    'message' => 'Format de note vocale non pris en charge',
                    'code' => '744f00bc-4389-4c74-92de-9a43cde55534',
                ],
            ],
            'detail' => 'voice_note: Format de note vocale non pris en charge',
            'type' => '/validation_errors/744f00bc-4389-4c74-92de-9a43cde55534',
            'title' => 'An error occurred',
            'description' => 'voice_note: Format de note vocale non pris en charge',
        ]);
        $this->assertNull(self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]));
    }

    /** Passes the type check on its header, then fails in ffmpeg: nothing is sent, nothing is stored. */
    public function test_a_broken_recording_is_refused_and_nothing_is_kept(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        $path = $this->temporaryPath();
        file_put_contents($path, substr((string) file_get_contents(self::FIXTURES . '/chrome-opus.webm'), 0, 64));

        $this->client->loginUser($member);
        $this->postVoiceNote($space, new UploadedFile($path, 'note.webm', 'audio/webm', null, true));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'La note vocale est illisible, veuillez réessayer',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'La note vocale est illisible, veuillez réessayer',
        ]);
        $this->assertNull(self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]));
        $this->assertNull(self::getContainer()->get(BandSpaceFileRepository::class)->findOneBy(['bandSpace' => $space->id]));
    }

    public function test_the_voice_note_is_streamed_inline(): void
    {
        $this->client->disableReboot();
        [$member, $space, $channel] = $this->memberWithChannel();
        [$file] = $this->seedVoiceNote($space, $channel, $member);

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/voice_notes/' . $file->id);

        $this->assertResponseIsSuccessful();
        $headers = $this->client->getResponse()->headers;
        $this->assertSame('audio/mp4', $headers->get('Content-Type'));
        $this->assertSame('inline; filename=note-vocale.m4a', $headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        $this->assertSame('max-age=86400, private', $headers->get('Cache-Control'));
        $this->assertSame(self::STORED_BYTES, $this->client->getInternalResponse()->getContent());
    }

    /** Each stream serves its own kind only: an image id is not a voice note. */
    public function test_a_chat_image_is_not_served_as_a_voice_note(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        [$file] = $this->seedVoiceNote($space, $channel, $member, mimeType: 'image/webp');

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/voice_notes/' . $file->id, [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Note vocale introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Note vocale introuvable',
        ]);
    }

    public function test_a_non_member_cannot_hear_the_voice_note(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        [$file] = $this->seedVoiceNote($space, $channel, $member);
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);

        $this->client->loginUser($outsider);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/voice_notes/' . $file->id, [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

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

    public function test_deleting_the_message_deletes_its_voice_note_for_good(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        [$file, $message] = $this->seedVoiceNote($space, $channel, $member);
        $fileId = (string) $file->id;

        $this->client->loginUser($member);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $space->id . '/chat/messages/' . $message->id, [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertNull(self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId), 'Purged, not merely trashed');
        $tombstone = self::getContainer()->get(MessageRepository::class)->find((string) $message->id);
        $this->assertInstanceOf(Message::class, $tombstone);
        $this->assertNull($tombstone->voiceNoteFileId);
        $this->assertNull($tombstone->voiceNoteDurationSeconds);
    }

    public function test_a_voice_note_deleted_from_files_reads_as_unavailable(): void
    {
        [$member, $space, $channel] = $this->memberWithChannel();
        [$file, $message] = $this->seedVoiceNote($space, $channel, $member);
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
                $this->expectedMessage($message, $space, $member, [
                    'file_id' => (string) $file->id,
                    'duration_seconds' => 42,
                    'is_available' => false,
                ], withContext: false),
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
     * What ChatMediaStore leaves behind, seeded directly: the file, its version, its `message`
     * attachment, the message pointing back at it, and the bytes in storage.
     *
     * @return array{BandSpaceFile, Message}
     */
    private function seedVoiceNote(BandSpace $space, MessageThread $channel, User $author, string $mimeType = 'audio/mp4'): array
    {
        $file = BandSpaceFileFactory::new(['bandSpace' => $space, 'createdBy' => $author, 'originalName' => 'note-vocale.m4a'])->create();
        $storagePath = 'chat-' . bin2hex(random_bytes(4)) . '.m4a';
        $version = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file,
            'createdBy' => $author,
            'mimeType' => $mimeType,
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
        $message->voiceNoteFileId = (string) $file->id;
        $message->voiceNoteDurationSeconds = 42;
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
     * @param array{file_id: string, duration_seconds: int, is_available: bool} $voiceNote
     *
     * @return array<string, mixed>
     */
    private function expectedMessage(Message $message, BandSpace $space, User $author, array $voiceNote, bool $withContext = true): array
    {
        $expected = [
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $author->id,
            'author_username' => $author->username,
            'author_profile_picture_url' => null,
            'content' => '',
            'creation_datetime' => $message->creationDatetime->format('c'),
            'reactions' => [],
            'attachments' => [],
            'update_datetime' => null,
            'editable_content' => '',
            'is_deleted' => false,
            'is_pinned' => false,
            'pinned_datetime' => null,
            'pinned_by_username' => null,
            'read_by_usernames' => [],
            'read_count' => 0,
            'image' => null,
            'voice_note' => $voiceNote,
        ];

        return $withContext ? ['@context' => '/api/contexts/ChatMessage'] + $expected : $expected;
    }

    private function postVoiceNote(BandSpace $space, UploadedFile $recording, ?string $content = null): void
    {
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $space->id . '/chat/messages',
            $content !== null ? ['content' => $content] : [],
            ['voice_note' => $recording],
            ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json'],
        );
    }

    /** A copy, since the upload is moved or deleted once handled. */
    private function recording(string $fixture, string $clientMimeType): UploadedFile
    {
        $path = $this->temporaryPath();
        copy(self::FIXTURES . '/' . $fixture, $path);

        return new UploadedFile($path, $fixture, $clientMimeType, null, true);
    }

    private function temporaryPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'chat_voice_note_api_test_');
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
