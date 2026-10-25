<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageFile;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageFileRepository;
use App\Repository\Message\MessageRepository;
use App\Service\Procedure\User\DeleteAccountProcedure;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageFileFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * An image in a direct conversation (#1247): only once the other person has replied, converted to
 * WebP as in the band chat, streamed inline to the two participants, and gone with its message or
 * with its sender's account.
 */
#[ResetDatabase]
class MessageImageTest extends ApiTestCase
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

    public function test_post_an_image_with_a_caption(): void
    {
        $this->client->disableReboot();
        [$thread, $me] = $this->conversationWithReply();

        $this->client->loginUser($me);
        $this->postImage($thread, $this->jpegUpload(3200, 2000, 'IMG_1234.jpg'), 'regarde ça');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = $this->sentBy($me, $thread);
        $file = self::getContainer()->get(MessageFileRepository::class)->findOneBy(['thread' => $thread->id]);
        $this->assertInstanceOf(MessageFile::class, $file);
        $this->assertJsonEquals($this->expectedPost($message, $thread, $me, 'regarde ça', 'regarde ça', [
            'file_id' => (string) $file->id,
            'is_available' => true,
        ]));

        $this->assertSame((string) $file->id, $message->imageFileId);
        $this->assertSame('IMG_1234.webp', $file->originalName);
        $this->assertSame('image/webp', $file->mimeType);
        $this->assertSame((string) $me->id, (string) $file->uploadedBy->id);
        // The converted WebP reached storage, under the conversation, not the JPEG that was sent.
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $stored = $filesystem->read('/message_files/' . $thread->id . '/' . $file->storagePath);
        $this->assertSame($file->size, strlen($stored));
        $this->assertSame([1600, 1000, IMAGETYPE_WEBP], array_slice((array) getimagesizefromstring($stored), 0, 3));
    }

    public function test_post_an_image_alone_reads_as_an_attachment(): void
    {
        $this->client->disableReboot();
        [$thread, $me] = $this->conversationWithReply();

        $this->client->loginUser($me);
        $this->postImage($thread, $this->jpegUpload(400, 300, 'photo.jpg'));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = $this->sentBy($me, $thread);
        $file = self::getContainer()->get(MessageFileRepository::class)->findOneBy(['thread' => $thread->id]);
        $this->assertInstanceOf(MessageFile::class, $file);
        $this->assertJsonEquals($this->expectedPost($message, $thread, $me, '', 'Pièce jointe', [
            'file_id' => (string) $file->id,
            'is_available' => true,
        ]));
    }

    public function test_a_blank_caption_is_stored_as_none(): void
    {
        $this->client->disableReboot();
        [$thread, $me] = $this->conversationWithReply();

        $this->client->loginUser($me);
        $this->postImage($thread, $this->jpegUpload(400, 300, 'photo.jpg'), "  \n ");

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = $this->sentBy($me, $thread);
        $file = self::getContainer()->get(MessageFileRepository::class)->findOneBy(['thread' => $thread->id]);
        $this->assertInstanceOf(MessageFile::class, $file);
        $this->assertJsonEquals($this->expectedPost($message, $thread, $me, '', 'Pièce jointe', [
            'file_id' => (string) $file->id,
            'is_available' => true,
        ]));
    }

    /**
     * The serializer turns a `data:` string into a File, which never names a file on disk, so the
     * constraint refuses it: an image only ever arrives as a real upload.
     */
    public function test_an_image_sent_as_json_text_is_refused(): void
    {
        [$thread, $me] = $this->conversationWithReply();

        $this->client->loginUser($me);
        $this->client->jsonRequest('POST', '/api/messages', [
            'thread' => '/api/message_threads/' . $thread->id,
            'image' => 'data:image/png;base64,AAAA',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/d2a3fb6e-7ddc-4210-8fbf-2ab345ce1998',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'image',
                    'message' => 'Le fichier n\'a pas été trouvé.',
                    'code' => 'd2a3fb6e-7ddc-4210-8fbf-2ab345ce1998',
                ],
            ],
            'detail' => 'image: Le fichier n\'a pas été trouvé.',
            'description' => 'image: Le fichier n\'a pas été trouvé.',
            'type' => '/validation_errors/d2a3fb6e-7ddc-4210-8fbf-2ab345ce1998',
            'title' => 'An error occurred',
        ]);
        $this->assertNull(self::getContainer()->get(MessageFileRepository::class)->findOneBy(['thread' => $thread->id]));
    }

    public function test_no_image_before_the_other_person_replied(): void
    {
        [$thread, $me] = $this->conversation();
        MessageFactory::new(['thread' => $thread, 'author' => $me, 'content' => 'salut, tu joues encore ?'])->create();

        $this->client->loginUser($me);
        $this->postImage($thread, $this->jpegUpload(400, 300, 'photo.jpg'));

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
        $this->assertCount(1, self::getContainer()->get(MessageRepository::class)->findBy(['thread' => $thread->id]));
        $this->assertNull(self::getContainer()->get(MessageFileRepository::class)->findOneBy(['thread' => $thread->id]));
    }

    public function test_a_blocked_pair_cannot_send_an_image(): void
    {
        [$thread, $me, $other] = $this->conversationWithReply();
        UserBlockFactory::new()->create(['blocker' => $other, 'blocked' => $me]);

        $this->client->loginUser($me);
        $this->postImage($thread, $this->jpegUpload(400, 300, 'photo.jpg'));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 403,
            'type' => '/errors/403',
            'detail' => 'Tu ne peux pas échanger de messages avec cet utilisateur',
            'description' => 'Tu ne peux pas échanger de messages avec cet utilisateur',
        ]);
        $this->assertNull(self::getContainer()->get(MessageFileRepository::class)->findOneBy(['thread' => $thread->id]));
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        [$thread, $me] = $this->conversationWithReply();
        $path = $this->temporaryPath();
        file_put_contents($path, 'pas une image');

        $this->client->loginUser($me);
        $this->postImage($thread, new UploadedFile($path, 'photo.png', 'image/png', null, true));

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
        $this->assertNull(self::getContainer()->get(MessageFileRepository::class)->findOneBy(['thread' => $thread->id]));
    }

    public function test_a_message_with_neither_text_nor_media_is_still_refused(): void
    {
        [$thread, $me] = $this->conversationWithReply();

        $this->client->loginUser($me);
        $this->client->request(
            'POST',
            '/api/messages',
            ['thread' => '/api/message_threads/' . $thread->id, 'content' => ''],
            [],
            ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Cette valeur ne doit pas être vide.',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'content: Cette valeur ne doit pas être vide.',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'content: Cette valeur ne doit pas être vide.',
        ]);
    }

    public function test_the_image_is_streamed_inline_to_the_other_person(): void
    {
        [$thread, $me, $other] = $this->conversationWithReply();
        $file = $this->seedImage($thread, $me);

        $this->client->loginUser($other);
        $this->client->request('GET', '/api/message_threads/' . $thread->id . '/images/' . $file->id);

        $this->assertResponseIsSuccessful();
        $headers = $this->client->getResponse()->headers;
        $this->assertSame('image/webp', $headers->get('Content-Type'));
        $this->assertSame('inline; filename=photo.webp', $headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        $this->assertSame('max-age=86400, private', $headers->get('Cache-Control'));
        $this->assertSame(self::STORED_BYTES, $this->client->getInternalResponse()->getContent());
    }

    public function test_a_stranger_cannot_see_the_image(): void
    {
        [$thread, $me] = $this->conversationWithReply();
        $file = $this->seedImage($thread, $me);

        $this->client->loginUser($this->user('intrus'));
        $this->client->request('GET', '/api/message_threads/' . $thread->id . '/images/' . $file->id, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Thread not found.',
            'description' => 'Thread not found.',
        ]);
    }

    public function test_a_blocked_pair_cannot_see_the_image(): void
    {
        [$thread, $me, $other] = $this->conversationWithReply();
        $file = $this->seedImage($thread, $me);
        UserBlockFactory::new()->create(['blocker' => $me, 'blocked' => $other]);

        $this->client->loginUser($other);
        $this->client->request('GET', '/api/message_threads/' . $thread->id . '/images/' . $file->id, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 403,
            'type' => '/errors/403',
            'detail' => 'Tu ne peux pas échanger de messages avec cet utilisateur',
            'description' => 'Tu ne peux pas échanger de messages avec cet utilisateur',
        ]);
    }

    public function test_an_image_of_another_conversation_is_not_found(): void
    {
        [$thread, $me] = $this->conversationWithReply();
        [$elsewhere] = $this->conversation('carla_keys', 'dan_guitar');
        $file = $this->seedImage($elsewhere, $me);

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

    public function test_a_band_channel_has_no_conversation_media(): void
    {
        $space = BandSpaceFactory::new()->create();
        $channel = MessageThreadFactory::new()->forBandSpace($space)->create();
        $me = $this->user('alice_drums');
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $me])->create();
        $file = MessageFileFactory::new(['thread' => $channel, 'uploadedBy' => $me])->create();

        $this->client->loginUser($me);
        $this->client->request('GET', '/api/message_threads/' . $channel->id . '/images/' . $file->id, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Thread not found.',
            'description' => 'Thread not found.',
        ]);
    }

    public function test_deleting_the_message_deletes_its_image_for_good(): void
    {
        $this->client->disableReboot();
        [$thread, $me] = $this->conversationWithReply();
        $file = $this->seedImage($thread, $me);
        $fileId = (string) $file->id;
        $path = '/message_files/' . $thread->id . '/' . $file->storagePath;
        $message = $this->sentBy($me, $thread);

        $this->client->loginUser($me);
        $this->client->request('DELETE', '/api/message_threads/' . $thread->id . '/messages/' . $message->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertNull(self::getContainer()->get(MessageFileRepository::class)->find($fileId));
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $this->assertFalse($filesystem->fileExists($path));
        $deleted = self::getContainer()->get(MessageRepository::class)->find($message->id);
        $this->assertNull($deleted?->imageFileId);
    }

    public function test_the_images_of_a_deleted_account_read_as_deleted(): void
    {
        [$thread, $me, $other] = $this->conversationWithReply();
        $file = $this->seedImage($thread, $me);
        $fileId = (string) $file->id;
        $path = '/message_files/' . $thread->id . '/' . $file->storagePath;
        $message = $this->sentBy($me, $thread);
        $reply = $this->sentBy($other, $thread);
        self::getContainer()->get(DeleteAccountProcedure::class)->process($me);

        $this->assertNull(self::getContainer()->get(MessageFileRepository::class)->find($fileId));
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $this->assertFalse($filesystem->fileExists($path));

        $this->client->loginUser($other);
        $this->client->request('GET', '/api/messages/' . $thread->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $thread->id,
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/messages/' . $reply->id,
                    '@type' => 'Message',
                    'id' => (string) $reply->id,
                    'creation_datetime' => '2026-10-20T19:00:00+00:00',
                    'author' => [
                        '@id' => '/api/users/' . $other->id,
                        '@type' => 'User',
                        'id' => $other->id,
                        'username' => 'bob_bass',
                        'display_name' => 'bob_bass',
                    ],
                    'content' => 'oui, envoie une photo',
                    'is_seen' => false,
                    'is_deleted' => false,
                    'reactions' => [],
                    'editable_content' => 'oui, envoie une photo',
                ],
                [
                    '@id' => '/api/messages/' . $message->id,
                    '@type' => 'Message',
                    'id' => (string) $message->id,
                    'creation_datetime' => '2026-10-20T20:00:00+00:00',
                    'author' => [
                        '@id' => '/api/users/' . $me->id,
                        '@type' => 'User',
                        'id' => $me->id,
                        'username' => 'deleted_' . $me->id,
                        'display_name' => 'Utilisateur supprimé',
                        'deletion_datetime' => $me->deletionDatetime?->format('c'),
                    ],
                    'content' => '',
                    'is_seen' => false,
                    'is_deleted' => false,
                    'reactions' => [],
                    'image' => ['file_id' => $fileId, 'is_available' => false],
                ],
            ],
            'totalItems' => 2,
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/messages/' . $thread->id . '{?order[creation_datetime],order[id]}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'order[creation_datetime]',
                        'property' => 'creation_datetime',
                        'required' => false,
                    ],
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'order[id]',
                        'property' => 'id',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    /** @return array{0: MessageThread, 1: User, 2: User} */
    private function conversation(string $me = 'alice_drums', string $other = 'bob_bass'): array
    {
        $first = $this->user($me);
        $second = $this->user($other);
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $first])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $second])->create();

        return [$thread, $first, $second];
    }

    /** @return array{0: MessageThread, 1: User, 2: User} */
    private function conversationWithReply(): array
    {
        [$thread, $me, $other] = $this->conversation();
        MessageFactory::new([
            'thread' => $thread,
            'author' => $other,
            'content' => 'oui, envoie une photo',
            'creationDatetime' => new \DateTime('2026-10-20 19:00:00'),
        ])->create();

        return [$thread, $me, $other];
    }

    private function user(string $username): User
    {
        return UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
    }

    /** What DirectMessageMediaStore leaves behind: the file row, its bytes, and the message carrying it. */
    private function seedImage(MessageThread $thread, User $author): MessageFile
    {
        $storagePath = 'chat-' . bin2hex(random_bytes(4)) . '.webp';
        $file = MessageFileFactory::new([
            'thread' => $thread,
            'uploadedBy' => $author,
            'size' => strlen(self::STORED_BYTES),
            'storagePath' => $storagePath,
        ])->create();
        MessageFactory::new([
            'thread' => $thread,
            'author' => $author,
            'content' => '',
            'imageFileId' => (string) $file->id,
            'creationDatetime' => new \DateTime('2026-10-20 20:00:00'),
        ])->create();

        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $filesystem->write('/message_files/' . $thread->id . '/' . $storagePath, self::STORED_BYTES);

        return $file;
    }

    private function sentBy(User $author, MessageThread $thread): Message
    {
        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $thread->id, 'author' => $author->id]);
        $this->assertInstanceOf(Message::class, $message);

        return $message;
    }

    /**
     * @param array{file_id: string, is_available: bool} $image
     *
     * @return array<string, mixed>
     */
    private function expectedPost(Message $message, MessageThread $thread, User $author, string $content, string $preview, array $image): array
    {
        return [
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $message->id,
            '@type' => 'Message',
            'id' => (string) $message->id,
            'creation_datetime' => $message->creationDatetime->format('c'),
            'author' => [
                '@id' => '/api/users/' . $author->id,
                '@type' => 'User',
                'id' => $author->id,
                'username' => $author->username,
                'display_name' => $author->username,
            ],
            'thread' => [
                '@id' => '/api/message_threads/' . $thread->id,
                '@type' => 'MessageThread',
                'id' => (string) $thread->id,
            ],
            'content' => $content,
            'content_preview' => $preview,
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => $content,
            'image' => $image,
        ];
    }

    private function postImage(MessageThread $thread, UploadedFile $image, ?string $content = null): void
    {
        $fields = ['thread' => '/api/message_threads/' . $thread->id];
        if ($content !== null) {
            $fields['content'] = $content;
        }
        $this->client->request(
            'POST',
            '/api/messages',
            $fields,
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
        $path = (string) tempnam(sys_get_temp_dir(), 'message_image_api_test_');
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
