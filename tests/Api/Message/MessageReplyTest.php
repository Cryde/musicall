<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageFile;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageFileRepository;
use App\Repository\Message\MessageParticipantRepository;
use App\Repository\Message\MessageRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Replying to a message of a conversation (#1380): the write, the 422s, and the quote on the reads.
 */
#[ResetDatabase]
class MessageReplyTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

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

    public function test_post_a_reply(): void
    {
        [$thread, $me, $lea] = $this->conversation();
        $answered = $this->message($thread, $lea, "on se voit samedi ?\nvers 14h", '2026-10-20 19:00:00');

        $this->client->loginUser($me);
        $this->post($thread, ['content' => 'oui, parfait', 'reply_to' => (string) $answered->id]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $reply = $this->sentBy($me, $thread);
        $this->assertSame((string) $answered->id, (string) $reply->replyTo?->id);
        $this->assertJsonEquals($this->expectedPost($reply, $thread, $me, 'oui, parfait', 'oui, parfait', [
            'reply_to' => $this->expectedQuote($answered, $lea, 'Léa Martin', 'on se voit samedi ? vers 14h'),
        ]));
    }

    public function test_a_photo_can_be_a_reply(): void
    {
        $this->client->disableReboot();
        [$thread, $me, $lea] = $this->conversation();
        $answered = $this->message($thread, $lea, 'tu as une photo de la salle ?', '2026-10-20 19:00:00');
        $path = (string) tempnam(sys_get_temp_dir(), 'message_reply_api_test_');
        $this->temporaryFiles[] = $path;
        imagejpeg(imagecreatetruecolor(16, 16), $path);

        $this->client->loginUser($me);
        $this->client->request(
            'POST',
            '/api/messages',
            ['thread' => '/api/message_threads/' . $thread->id, 'reply_to' => (string) $answered->id],
            ['image' => new UploadedFile($path, 'salle.jpg', 'image/jpeg', null, true)],
            ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $reply = $this->sentBy($me, $thread);
        $file = self::getContainer()->get(MessageFileRepository::class)->findOneBy(['thread' => $thread->id]);
        $this->assertInstanceOf(MessageFile::class, $file);
        $this->assertJsonEquals($this->expectedPost($reply, $thread, $me, '', 'Pièce jointe', [
            'image' => ['file_id' => (string) $file->id, 'is_available' => true],
            'reply_to' => $this->expectedQuote($answered, $lea, 'Léa Martin', 'tu as une photo de la salle ?'),
        ]));
    }

    /** A form that sends every field, filled or not: an empty reply_to answers nothing. */
    public function test_an_empty_reply_to_is_no_reply(): void
    {
        [$thread, $me] = $this->conversation();

        $this->client->loginUser($me);
        $this->post($thread, ['content' => 'salut', 'reply_to' => '']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = $this->sentBy($me, $thread);
        $this->assertNull($message->replyTo);
        $this->assertJsonEquals($this->expectedPost($message, $thread, $me, 'salut', 'salut'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidReplyTargets(): iterable
    {
        yield 'unknown message' => ['unknown'];
        yield 'message of another conversation' => ['other_thread'];
        yield 'deleted message' => ['deleted'];
        yield 'not an id' => ['not_an_id'];
    }

    #[DataProvider('invalidReplyTargets')]
    public function test_an_invalid_reply_target_is_refused(string $case): void
    {
        [$thread, $me, $lea] = $this->conversation();
        $replyTo = match ($case) {
            'unknown' => '0192f3a4-5b6c-7d8e-9f01-23456789abcd',
            'other_thread' => (string) $this->message(MessageThreadFactory::new()->create(), $lea, 'une autre conversation', '2026-10-20 19:00:00')->id,
            'deleted' => (string) $this->message($thread, $lea, '', '2026-10-20 19:00:00', deleted: true)->id,
            default => 'pas-un-id',
        };
        $messageRepository = self::getContainer()->get(MessageRepository::class);
        $messagesBefore = $messageRepository->count(['thread' => $thread->id]);

        $this->client->loginUser($me);
        $this->post($thread, ['content' => 'oui', 'reply_to' => $replyTo]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_e51d39ee-c6c8-407c-ac27-52a6db756708',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'reply_to',
                    'message' => 'Le message auquel tu réponds n\'est plus disponible',
                    'code' => 'music_all_e51d39ee-c6c8-407c-ac27-52a6db756708',
                ],
            ],
            'detail' => 'reply_to: Le message auquel tu réponds n\'est plus disponible',
            'type' => '/validation_errors/music_all_e51d39ee-c6c8-407c-ac27-52a6db756708',
            'title' => 'An error occurred',
            'description' => 'reply_to: Le message auquel tu réponds n\'est plus disponible',
        ]);
        $this->assertSame($messagesBefore, $messageRepository->count(['thread' => $thread->id]));
    }

    /**
     * Every kind of answered message, each on the page after the one holding the reply, read in the
     * query the page already was: the answered messages and their author are joined, never loaded
     * one by one.
     */
    public function test_the_list_quotes_every_kind_of_answered_message(): void
    {
        [$thread, $me, $lea] = $this->conversation();
        $text = $this->message($thread, $lea, str_repeat('la salle de répétition ', 10), '2026-10-20 19:00:00');
        $photo = $this->message($thread, $lea, '', '2026-10-20 19:01:00', imageFileId: '0192f3a4-0000-7000-8000-000000000001');
        $voiceNote = $this->message($thread, $lea, '', '2026-10-20 19:02:00', voiceNoteFileId: '0192f3a4-0000-7000-8000-000000000002');
        $deleted = $this->message($thread, $lea, '', '2026-10-20 19:03:00', deleted: true);
        $replies = [];
        foreach ([$text, $photo, $voiceNote, $deleted] as $minute => $answered) {
            $replies[] = $this->message($thread, $me, 'réponse ' . $minute, sprintf('2026-10-20 20:%02d:00', $minute), replyTo: $answered);
        }

        $this->coldRequestAs($me, '/api/messages/' . $thread->id . '?order[creation_datetime]=desc&order[id]=desc&itemsPerPage=4');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $thread->id,
            '@type' => 'Collection',
            'totalItems' => 8,
            'member' => [
                $this->expectedListed($replies[3], $me, 'réponse 3', $this->expectedQuote($deleted, $lea, 'Léa Martin', '', isDeleted: true)),
                $this->expectedListed($replies[2], $me, 'réponse 2', $this->expectedQuote($voiceNote, $lea, 'Léa Martin', '', hasVoiceNote: true)),
                $this->expectedListed($replies[1], $me, 'réponse 1', $this->expectedQuote($photo, $lea, 'Léa Martin', '', hasImage: true)),
                // One line of at most 140 characters, the last one an ellipsis.
                $this->expectedListed($replies[0], $me, 'réponse 0', $this->expectedQuote($text, $lea, 'Léa Martin', mb_substr(str_repeat('la salle de répétition ', 10), 0, 139) . '…')),
            ],
            'view' => [
                '@id' => '/api/messages/' . $thread->id . '?itemsPerPage=4&order%5Bcreation_datetime%5D=desc&order%5Bid%5D=desc&page=1',
                '@type' => 'PartialCollectionView',
                'first' => '/api/messages/' . $thread->id . '?itemsPerPage=4&order%5Bcreation_datetime%5D=desc&order%5Bid%5D=desc&page=1',
                'last' => '/api/messages/' . $thread->id . '?itemsPerPage=4&order%5Bcreation_datetime%5D=desc&order%5Bid%5D=desc&page=2',
                'next' => '/api/messages/' . $thread->id . '?itemsPerPage=4&order%5Bcreation_datetime%5D=desc&order%5Bid%5D=desc&page=2',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/messages/' . $thread->id . '{?order[creation_datetime],order[id]}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    ['@type' => 'IriTemplateMapping', 'variable' => 'order[creation_datetime]', 'property' => 'creation_datetime', 'required' => false],
                    ['@type' => 'IriTemplateMapping', 'variable' => 'order[id]', 'property' => 'id', 'required' => false],
                ],
            ],
        ]);
        $this->assertSame([], $this->queriesMatching('FROM message t0'), 'An answered message must come with the page, never in a query of its own');
        $this->assertNoQueryReadsTable('user_profile', 'An answered author\'s profile must come with the page, never in a query of its own');
    }

    /** The inbox previews a reply as any message, and never loads what it answers. */
    public function test_the_inbox_does_not_load_the_answered_message(): void
    {
        [$thread, $me, $lea] = $this->conversation();
        $answered = $this->message($thread, $lea, 'on se voit samedi ?', '2026-10-20 19:00:00');
        $reply = $this->message($thread, $me, 'oui', '2026-10-20 19:01:00', replyTo: $answered);
        $thread->lastMessage = $reply;
        $meta = MessageThreadMetaFactory::new(['thread' => $thread, 'user' => $me])->create();
        $participants = self::getContainer()->get(MessageParticipantRepository::class)->findBy(['thread' => $thread->id]);
        $participantIri = static function (User $user) use ($participants): string {
            foreach ($participants as $participant) {
                if ($participant->participant->id === $user->id) {
                    return '/api/message_participants/' . $participant->id;
                }
            }

            return '';
        };
        $user = static fn (User $user, string $displayName): array => [
            '@id' => '/api/users/' . $user->id,
            '@type' => 'User',
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $displayName,
        ];

        $this->coldRequestAs($me, '/api/message_thread_metas');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/message_thread_metas/' . $meta->id,
                    '@type' => 'MessageThreadMeta',
                    'id' => (string) $meta->id,
                    'unread_count' => 1,
                    'thread' => [
                        '@id' => '/api/message_threads/' . $thread->id,
                        '@type' => 'MessageThread',
                        'id' => (string) $thread->id,
                        'message_participants' => [
                            // By username, as the inbox orders them.
                            ['@id' => $participantIri($lea), '@type' => 'MessageParticipant', 'participant' => $user($lea, 'Léa Martin')],
                            ['@id' => $participantIri($me), '@type' => 'MessageParticipant', 'participant' => $user($me, 'moi')],
                        ],
                        'last_message' => [
                            '@id' => '/api/messages/' . $reply->id,
                            '@type' => 'Message',
                            'creation_datetime' => '2026-10-20T19:01:00+00:00',
                            'author' => $user($me, 'moi'),
                            'content' => 'oui',
                            'content_preview' => 'oui',
                        ],
                        'can_send_media' => true,
                    ],
                ],
            ],
            'totalItems' => 1,
        ]);
        $this->assertSame([], $this->queriesMatching('FROM message t0'), 'The inbox must not load the message a preview answers');
    }

    /** An edit changes the text, never what it answers: the PATCH takes no reply_to. */
    public function test_an_edit_keeps_the_quote(): void
    {
        [$thread, $me, $lea] = $this->conversation();
        $answered = $this->message($thread, $lea, 'on se voit samedi ?', '2026-10-20 19:00:00');
        $other = $this->message($thread, $lea, 'ou dimanche ?', '2026-10-20 19:01:00');
        $reply = $this->message($thread, $me, 'oui', '2026-10-20 19:02:00', replyTo: $answered);

        $this->client->loginUser($me);
        $this->client->jsonRequest(
            'PATCH',
            '/api/message_threads/' . $thread->id . '/messages/' . $reply->id,
            ['content' => 'oui, samedi', 'reply_to' => (string) $other->id],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $edited = self::getContainer()->get(MessageRepository::class)->find($reply->id);
        $this->assertInstanceOf(Message::class, $edited);
        $this->assertSame((string) $answered->id, (string) $edited->replyTo?->id);
        $this->assertNotNull($edited->updateDatetime);
        $this->assertJsonEquals(['@context' => '/api/contexts/Message'] + $this->expectedListed(
            $edited,
            $me,
            'oui, samedi',
            $this->expectedQuote($answered, $lea, 'Léa Martin', 'on se voit samedi ?'),
        ) + ['update_datetime' => $edited->updateDatetime->format('c')]);
    }

    /**
     * Me and Léa, whose public profile carries a name: the quote names her the way the conversation does.
     *
     * @return array{MessageThread, User, User}
     */
    private function conversation(): array
    {
        $me = UserFactory::new()->asBaseUser()->create(['username' => 'moi', 'email' => 'moi@test.com']);
        $lea = UserFactory::new()->asBaseUser()->create(['username' => 'lea', 'email' => 'lea@test.com']);
        $lea->profile->displayName = 'Léa Martin';
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $me])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $lea])->create();

        return [$thread, $me, $lea];
    }

    private function message(
        MessageThread $thread,
        User $author,
        string $content,
        string $creationDatetime,
        ?Message $replyTo = null,
        bool $deleted = false,
        ?string $imageFileId = null,
        ?string $voiceNoteFileId = null,
    ): Message {
        return MessageFactory::new([
            'thread' => $thread,
            'author' => $author,
            'content' => $content,
            'creationDatetime' => new \DateTime($creationDatetime),
            'replyTo' => $replyTo,
            'deletionDatetime' => $deleted ? new \DateTimeImmutable($creationDatetime) : null,
            'imageFileId' => $imageFileId,
            'voiceNoteFileId' => $voiceNoteFileId,
            'voiceNoteDurationSeconds' => $voiceNoteFileId !== null ? 12 : null,
            'voiceNotePeaks' => $voiceNoteFileId !== null ? [10, 200] : null,
        ])->create();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(MessageThread $thread, array $body): void
    {
        $this->client->jsonRequest(
            'POST',
            '/api/messages',
            ['thread' => '/api/message_threads/' . $thread->id] + $body,
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );
    }

    private function sentBy(User $author, MessageThread $thread): Message
    {
        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $thread->id, 'author' => $author->id]);
        $this->assertInstanceOf(Message::class, $message);

        return $message;
    }

    /**
     * Logs in and profiles with an empty identity map: the factories leave every message managed, and
     * a warm map would answer the answered messages from memory and hide the very thing under test.
     */
    private function coldRequestAs(User $viewer, string $uri): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $viewerId = (string) $viewer->id;
        $entityManager->clear();

        $this->client->loginUser($entityManager->find(User::class, $viewerId));
        $this->client->enableProfiler();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->request('GET', $uri, [], [], ['HTTP_ACCEPT' => 'application/ld+json']);
    }

    /**
     * @return array{id: string, author_id: string, author_display_name: string, excerpt: string, has_image: bool, has_voice_note: bool, is_deleted: bool}
     */
    private function expectedQuote(
        Message $answered,
        User $author,
        string $authorDisplayName,
        string $excerpt,
        bool $hasImage = false,
        bool $hasVoiceNote = false,
        bool $isDeleted = false,
    ): array {
        return [
            'id' => (string) $answered->id,
            'author_id' => (string) $author->id,
            'author_display_name' => $authorDisplayName,
            'excerpt' => $excerpt,
            'has_image' => $hasImage,
            'has_voice_note' => $hasVoiceNote,
            'is_deleted' => $isDeleted,
        ];
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function expectedPost(Message $message, MessageThread $thread, User $author, string $content, string $preview, array $extra = []): array
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
                'id' => $thread->id,
            ],
            'content' => $content,
            'content_preview' => $preview,
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => $content,
        ] + $extra;
    }

    /**
     * A message of mine as the list shows it.
     *
     * @param array<string, mixed> $replyTo
     *
     * @return array<string, mixed>
     */
    private function expectedListed(Message $message, User $author, string $content, array $replyTo): array
    {
        return [
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
            'content' => $content,
            'is_seen' => false,
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => $content,
            'reply_to' => $replyTo,
        ];
    }

    /**
     * @return list<string>
     */
    private function queriesMatching(string $needle): array
    {
        $profile = $this->client->getProfile();
        $this->assertNotFalse($profile, 'The profiler must be enabled to inspect the queries.');

        $matching = [];
        foreach ($profile->getCollector('db')->getQueries()['default'] ?? [] as $query) {
            $sql = (string) $query['sql'];
            if (str_contains($sql, $needle)) {
                $matching[] = $sql;
            }
        }

        return $matching;
    }
}
