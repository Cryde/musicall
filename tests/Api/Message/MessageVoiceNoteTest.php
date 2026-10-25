<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageFile;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageFileRepository;
use App\Repository\Message\MessageRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageFileFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A voice note in a direct conversation (#1247): converted to AAC and measured as in the band chat,
 * sent alone, streamed inline to the two participants, and gone with its message.
 */
#[ResetDatabase]
class MessageVoiceNoteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string FIXTURES = __DIR__ . '/../../fixtures/voice_notes';

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
        [$thread, $me] = $this->conversationWithReply();

        $this->client->loginUser($me);
        $this->postVoiceNote($thread, $this->recording('chrome-opus.webm', 'audio/webm'));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $thread->id, 'author' => $me->id]);
        $this->assertInstanceOf(Message::class, $message);
        $file = self::getContainer()->get(MessageFileRepository::class)->findOneBy(['thread' => $thread->id]);
        $this->assertInstanceOf(MessageFile::class, $file);

        // Read back and checked for shape rather than pinned, as in ChatVoiceNoteTest: the exact values
        // depend on the ffmpeg build's AAC encoder.
        $peaks = $message->voiceNotePeaks;
        $this->assertIsArray($peaks);
        $this->assertCount(48, $peaks);
        $this->assertSame(255, max($peaks));
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $message->id,
            '@type' => 'Message',
            'id' => (string) $message->id,
            'creation_datetime' => $message->creationDatetime->format('c'),
            'author' => [
                '@id' => '/api/users/' . $me->id,
                '@type' => 'User',
                'id' => $me->id,
                'username' => 'alice_drums',
                'display_name' => 'alice_drums',
            ],
            'thread' => [
                '@id' => '/api/message_threads/' . $thread->id,
                '@type' => 'MessageThread',
                'id' => (string) $thread->id,
            ],
            'content' => '',
            'content_preview' => 'Pièce jointe',
            'is_deleted' => false,
            'reactions' => [],
            'voice_note' => [
                'file_id' => (string) $file->id,
                'duration_seconds' => 2,
                'peaks' => $peaks,
                'is_available' => true,
            ],
        ]);

        $this->assertSame('audio/mp4', $file->mimeType);
        $this->assertMatchesRegularExpression('/^note-vocale-\d{4}-\d{2}-\d{2}-\d{6}\.m4a$/', $file->originalName);
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $stored = $filesystem->read('/message_files/' . $thread->id . '/' . $file->storagePath);
        $this->assertSame($file->size, strlen($stored));
        $this->assertSame('ftyp', substr($stored, 4, 4), 'An MP4 container');
    }

    public function test_no_voice_note_before_the_other_person_replied(): void
    {
        $me = $this->user('alice_drums');
        $other = $this->user('bob_bass');
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $me])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $other])->create();
        MessageFactory::new(['thread' => $thread, 'author' => $me, 'content' => 'salut, tu joues encore ?'])->create();

        $this->client->loginUser($me);
        $this->postVoiceNote($thread, $this->recording('chrome-opus.webm', 'audio/webm'));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 403,
            'type' => '/errors/403',
            'detail' => 'Tu pourras envoyer des images et des notes vocales une fois que cette personne t\'aura répondu',
            'description' => 'Tu pourras envoyer des images et des notes vocales une fois que cette personne t\'aura répondu',
        ]);
        $this->assertNull(self::getContainer()->get(MessageFileRepository::class)->findOneBy(['thread' => $thread->id]));
    }

    public function test_a_voice_note_with_text_is_refused(): void
    {
        [$thread, $me] = $this->conversationWithReply();

        $this->client->loginUser($me);
        $this->postVoiceNote($thread, $this->recording('chrome-opus.webm', 'audio/webm'), 'écoute ça');

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
        $this->assertNull(self::getContainer()->get(MessageFileRepository::class)->findOneBy(['thread' => $thread->id]));
    }

    public function test_a_file_that_is_not_a_recording_is_refused(): void
    {
        [$thread, $me] = $this->conversationWithReply();
        $path = $this->temporaryPath();
        file_put_contents($path, 'pas un enregistrement');

        $this->client->loginUser($me);
        $this->postVoiceNote($thread, new UploadedFile($path, 'note.webm', 'audio/webm', null, true));

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
    }

    /** Passes the type check on its header, then fails in ffmpeg: nothing is sent, nothing is stored. */
    public function test_a_broken_recording_is_refused_and_nothing_is_kept(): void
    {
        [$thread, $me] = $this->conversationWithReply();
        $path = $this->temporaryPath();
        file_put_contents($path, substr((string) file_get_contents(self::FIXTURES . '/chrome-opus.webm'), 0, 64));

        $this->client->loginUser($me);
        $this->postVoiceNote($thread, new UploadedFile($path, 'note.webm', 'audio/webm', null, true));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'La note vocale est illisible, réessaie',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'La note vocale est illisible, réessaie',
        ]);
        $this->assertNull(self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $thread->id, 'author' => $me->id]));
        $this->assertNull(self::getContainer()->get(MessageFileRepository::class)->findOneBy(['thread' => $thread->id]));
    }

    public function test_the_voice_note_is_streamed_inline(): void
    {
        [$thread, $me, $other] = $this->conversationWithReply();
        $file = $this->seedVoiceNote($thread, $me);

        $this->client->loginUser($other);
        $this->client->request('GET', '/api/message_threads/' . $thread->id . '/voice_notes/' . $file->id);

        $this->assertResponseIsSuccessful();
        $headers = $this->client->getResponse()->headers;
        $this->assertSame('audio/mp4', $headers->get('Content-Type'));
        $this->assertSame('inline; filename=note-vocale.m4a', $headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        $this->assertSame('max-age=86400, private', $headers->get('Cache-Control'));
        $this->assertSame(self::STORED_BYTES, $this->client->getInternalResponse()->getContent());
    }

    public function test_an_image_is_not_served_as_a_voice_note(): void
    {
        [$thread, $me] = $this->conversationWithReply();
        $image = MessageFileFactory::new(['thread' => $thread, 'uploadedBy' => $me])->create();

        $this->client->loginUser($me);
        $this->client->request('GET', '/api/message_threads/' . $thread->id . '/voice_notes/' . $image->id, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Note vocale introuvable',
            'description' => 'Note vocale introuvable',
        ]);
    }

    public function test_a_voice_note_is_not_served_as_an_image(): void
    {
        [$thread, $me] = $this->conversationWithReply();
        $file = $this->seedVoiceNote($thread, $me);

        $this->client->loginUser($me);
        $this->client->request('GET', '/api/message_threads/' . $thread->id . '/images/' . $file->id, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Image introuvable',
            'description' => 'Image introuvable',
        ]);
    }

    public function test_deleting_the_message_deletes_its_voice_note_for_good(): void
    {
        $this->client->disableReboot();
        [$thread, $me] = $this->conversationWithReply();
        $file = $this->seedVoiceNote($thread, $me);
        $fileId = (string) $file->id;
        $path = '/message_files/' . $thread->id . '/' . $file->storagePath;
        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $thread->id, 'author' => $me->id]);
        $this->assertInstanceOf(Message::class, $message);

        $this->client->loginUser($me);
        $this->client->request('DELETE', '/api/message_threads/' . $thread->id . '/messages/' . $message->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertNull(self::getContainer()->get(MessageFileRepository::class)->find($fileId));
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $this->assertFalse($filesystem->fileExists($path));
        $deleted = self::getContainer()->get(MessageRepository::class)->find($message->id);
        $this->assertNull($deleted?->voiceNoteFileId);
        $this->assertNull($deleted?->voiceNotePeaks);
    }

    /** @return array{0: MessageThread, 1: User, 2: User} */
    private function conversationWithReply(): array
    {
        $me = $this->user('alice_drums');
        $other = $this->user('bob_bass');
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $me])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $other])->create();
        MessageFactory::new([
            'thread' => $thread,
            'author' => $other,
            'content' => 'tu as enregistré la démo ?',
            'creationDatetime' => new \DateTime('2026-10-20 19:00:00'),
        ])->create();

        return [$thread, $me, $other];
    }

    private function user(string $username): User
    {
        return UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
    }

    /** What DirectMessageMediaStore leaves behind: the file row, its bytes, and the message carrying it. */
    private function seedVoiceNote(MessageThread $thread, User $author): MessageFile
    {
        $storagePath = 'chat-' . bin2hex(random_bytes(4)) . '.m4a';
        $file = MessageFileFactory::new([
            'thread' => $thread,
            'uploadedBy' => $author,
            'mimeType' => 'audio/mp4',
            'originalName' => 'note-vocale.m4a',
            'size' => strlen(self::STORED_BYTES),
            'storagePath' => $storagePath,
        ])->create();
        MessageFactory::new([
            'thread' => $thread,
            'author' => $author,
            'content' => '',
            'voiceNoteFileId' => (string) $file->id,
            'voiceNoteDurationSeconds' => 42,
            'voiceNotePeaks' => [0, 64, 255, 128],
            'creationDatetime' => new \DateTime('2026-10-20 20:00:00'),
        ])->create();

        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $filesystem->write('/message_files/' . $thread->id . '/' . $storagePath, self::STORED_BYTES);

        return $file;
    }

    private function postVoiceNote(MessageThread $thread, UploadedFile $recording, ?string $content = null): void
    {
        $fields = ['thread' => '/api/message_threads/' . $thread->id];
        if ($content !== null) {
            $fields['content'] = $content;
        }
        $this->client->request(
            'POST',
            '/api/messages',
            $fields,
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
        $path = (string) tempnam(sys_get_temp_dir(), 'message_voice_note_api_test_');
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
