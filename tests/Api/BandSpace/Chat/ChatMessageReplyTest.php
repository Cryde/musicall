<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\Message\MessageRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageMentionFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Replying to a message of the band chat (#1377): the write, the 422s, and the quote on every read.
 */
#[ResetDatabase]
class ChatMessageReplyTest extends ApiTestCase
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
        [$batteur, $bassiste, $space, $channel] = $this->band();
        $answered = $this->message($channel, $bassiste, '@[' . $batteur->id . '] on répète samedi à 14h ?', '2026-09-10 20:00:00');
        MessageMentionFactory::new(['message' => $answered, 'mentionedUser' => $batteur])->create();

        $this->client->loginUser($batteur);
        $this->post($space, ['content' => 'oui, je prends la clé', 'reply_to' => (string) $answered->id]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $reply = $this->replyIn($channel);
        $this->assertSame((string) $answered->id, (string) $reply->replyTo?->id);
        $this->assertJsonEquals(['@context' => '/api/contexts/ChatMessage'] + $this->expectedMessage(
            $reply,
            $space,
            $batteur,
            'oui, je prends la clé',
            editableContent: 'oui, je prends la clé',
            replyTo: $this->expectedQuote($answered, $bassiste, '@batteur on répète samedi à 14h ?'),
        ));
    }

    public function test_a_photo_can_be_a_reply(): void
    {
        [$batteur, $bassiste, $space, $channel] = $this->band();
        $answered = $this->message($channel, $bassiste, 'quelqu’un a une photo de la scène ?', '2026-09-10 20:00:00');
        $path = (string) tempnam(sys_get_temp_dir(), 'chat_reply_api_test_');
        $this->temporaryFiles[] = $path;
        imagejpeg(imagecreatetruecolor(16, 16), $path);

        $this->client->loginUser($batteur);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $space->id . '/chat/messages',
            ['reply_to' => (string) $answered->id],
            ['image' => new UploadedFile($path, 'scene.jpg', 'image/jpeg', null, true)],
            ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $reply = $this->replyIn($channel);
        $file = self::getContainer()->get(BandSpaceFileRepository::class)->findOneBy(['bandSpace' => $space->id]);
        $this->assertInstanceOf(BandSpaceFile::class, $file);
        $this->assertJsonEquals(['@context' => '/api/contexts/ChatMessage'] + $this->expectedMessage(
            $reply,
            $space,
            $batteur,
            '',
            editableContent: '',
            replyTo: $this->expectedQuote($answered, $bassiste, 'quelqu’un a une photo de la scène ?'),
            image: ['file_id' => (string) $file->id, 'is_available' => true],
        ));
    }

    /** A form that sends every field, filled or not: an empty reply_to answers nothing. */
    public function test_an_empty_reply_to_is_no_reply(): void
    {
        [$batteur, , $space, $channel] = $this->band();

        $this->client->loginUser($batteur);
        $this->post($space, ['content' => 'salut', 'reply_to' => '']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]);
        $this->assertInstanceOf(Message::class, $message);
        $this->assertNull($message->replyTo);
        $this->assertJsonEquals(['@context' => '/api/contexts/ChatMessage'] + $this->expectedMessage(
            $message,
            $space,
            $batteur,
            'salut',
            editableContent: 'salut',
        ));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidReplyTargets(): iterable
    {
        yield 'unknown message' => ['unknown'];
        yield 'message of another band' => ['other_band'];
        yield 'deleted message' => ['deleted'];
        yield 'not an id' => ['not_an_id'];
    }

    #[DataProvider('invalidReplyTargets')]
    public function test_an_invalid_reply_target_is_refused(string $case): void
    {
        [$batteur, $bassiste, $space, $channel] = $this->band();
        $replyTo = match ($case) {
            'unknown' => '0192f3a4-5b6c-7d8e-9f01-23456789abcd',
            'other_band' => (string) $this->message(
                MessageThreadFactory::new()->forBandSpace(BandSpaceFactory::new()->create())->create(),
                $bassiste,
                'leur répétition',
                '2026-09-10 20:00:00',
            )->id,
            'deleted' => (string) $this->message($channel, $bassiste, '', '2026-09-10 20:00:00', deleted: true)->id,
            default => 'pas-un-id',
        };
        $messagesBefore = $this->countMessagesIn($channel);

        $this->client->loginUser($batteur);
        $this->post($space, ['content' => 'oui', 'reply_to' => $replyTo]);

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
        $this->assertSame($messagesBefore, $this->countMessagesIn($channel));
    }

    /**
     * Every kind of answered message on one page, read in the one query the page already was: the
     * answered messages and their authors are joined, never loaded one by one.
     */
    public function test_the_list_quotes_every_kind_of_answered_message(): void
    {
        [$batteur, $bassiste, $space, $channel] = $this->band();
        $long = '@[' . $batteur->id . '] ' . str_repeat('la balance ', 20);
        $text = $this->message($channel, $bassiste, $long, '2026-09-10 20:00:00');
        MessageMentionFactory::new(['message' => $text, 'mentionedUser' => $batteur])->create();
        $photo = $this->message($channel, $bassiste, '', '2026-09-10 20:01:00', imageFileId: '0192f3a4-0000-7000-8000-000000000001');
        $voiceNote = $this->message($channel, $bassiste, '', '2026-09-10 20:02:00', voiceNoteFileId: '0192f3a4-0000-7000-8000-000000000002');
        $attachmentOnly = $this->message($channel, $bassiste, '', '2026-09-10 20:03:00');
        $deleted = $this->message($channel, $bassiste, '', '2026-09-10 20:04:00', deleted: true);
        $replies = [];
        foreach ([$text, $photo, $voiceNote, $attachmentOnly, $deleted] as $minute => $answered) {
            $replies[] = $this->message($channel, $batteur, 'réponse ' . $minute, sprintf('2026-09-10 21:%02d:00', $minute), replyTo: $answered);
        }

        $this->client->loginUser($batteur);
        $this->client->enableProfiler();
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $quote = static fn (Message $answered, string $excerpt, bool $hasImage = false, bool $hasVoiceNote = false, bool $isDeleted = false): array => [
            'id' => (string) $answered->id,
            'author_id' => (string) $answered->author->id,
            'author_display_name' => 'bassiste',
            'excerpt' => $excerpt,
            'has_image' => $hasImage,
            'has_voice_note' => $hasVoiceNote,
            'is_deleted' => $isDeleted,
        ];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/messages',
            '@type' => 'Collection',
            'totalItems' => 10,
            'member' => [
                $this->expectedMessage($replies[4], $space, $batteur, 'réponse 4', editableContent: 'réponse 4', replyTo: $quote($deleted, '', isDeleted: true)),
                $this->expectedMessage($replies[3], $space, $batteur, 'réponse 3', editableContent: 'réponse 3', replyTo: $quote($attachmentOnly, '')),
                $this->expectedMessage($replies[2], $space, $batteur, 'réponse 2', editableContent: 'réponse 2', replyTo: $quote($voiceNote, '', hasVoiceNote: true)),
                $this->expectedMessage($replies[1], $space, $batteur, 'réponse 1', editableContent: 'réponse 1', replyTo: $quote($photo, '', hasImage: true)),
                // One line of at most 140 characters, the last one an ellipsis, the mention as a name.
                $this->expectedMessage($replies[0], $space, $batteur, 'réponse 0', editableContent: 'réponse 0', replyTo: $quote($text, mb_substr('@batteur ' . str_repeat('la balance ', 20), 0, 139) . '…')),
                $this->expectedMessage($deleted, $space, $bassiste, '', isDeleted: true),
                $this->expectedMessage($attachmentOnly, $space, $bassiste, ''),
                $this->expectedMessage($voiceNote, $space, $bassiste, '', voiceNote: [
                    'file_id' => '0192f3a4-0000-7000-8000-000000000002',
                    'duration_seconds' => 12,
                    'peaks' => [10, 200],
                    'is_available' => false,
                ]),
                $this->expectedMessage($photo, $space, $bassiste, '', image: ['file_id' => '0192f3a4-0000-7000-8000-000000000001', 'is_available' => false]),
                $this->expectedMessage($text, $space, $bassiste, '<span class="chat-mention">@batteur</span> ' . str_repeat('la balance ', 20)),
            ],
        ]);
        // The page and its count, nothing per reply.
        $this->assertCount(2, $this->queriesMatching(' FROM message m'));
        // At most two: the firewall hydrates the viewer and checkMember() the one membership, so the
        // answered messages' author never drags the profile tables along (#730).
        $this->assertLessThanOrEqual(2, count($this->queriesMatching('user_musician_profile')));
    }

    public function test_the_window_quotes_the_answered_message(): void
    {
        [$batteur, $bassiste, $space, $channel] = $this->band();
        $answered = $this->message($channel, $bassiste, 'on répète samedi ?', '2026-09-10 20:00:00');
        $reply = $this->message($channel, $batteur, 'oui', '2026-09-10 20:01:00', replyTo: $answered);

        $this->client->loginUser($batteur);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $space->id . '/chat/message_window?around=' . $reply->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessageWindow',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/message_window',
            '@type' => 'ChatMessageWindow',
            'band_space_id' => (string) $space->id,
            'messages' => [
                $this->expectedMessage($answered, $space, $bassiste, 'on répète samedi ?'),
                $this->expectedMessage($reply, $space, $batteur, 'oui', editableContent: 'oui', replyTo: $this->expectedQuote($answered, $bassiste, 'on répète samedi ?')),
            ],
            'has_older' => false,
            'has_newer' => false,
            'total_items' => 2,
        ]);
    }

    public function test_the_pinned_list_quotes_the_answered_message(): void
    {
        [$batteur, $bassiste, $space, $channel] = $this->band();
        $answered = $this->message($channel, $bassiste, 'quelle adresse ?', '2026-09-10 20:00:00');
        $reply = $this->message($channel, $batteur, '12 rue des Lilas', '2026-09-10 20:01:00', replyTo: $answered);
        $reply->pinnedDatetime = new \DateTimeImmutable('2026-09-11 09:00:00');
        $reply->pinnedBy = $batteur;
        \Zenstruck\Foundry\Persistence\save($reply);

        $this->client->loginUser($batteur);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/pinned_messages');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/pinned_messages',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    'is_pinned' => true,
                    'pinned_datetime' => '2026-09-11T09:00:00+00:00',
                    'pinned_by_username' => 'batteur',
                    'pinned_by_display_name' => 'batteur',
                ] + $this->expectedMessage($reply, $space, $batteur, '12 rue des Lilas', editableContent: '12 rue des Lilas', replyTo: $this->expectedQuote($answered, $bassiste, 'quelle adresse ?')),
            ],
        ]);
    }

    /** An edit changes the text, never what it answers: the PATCH takes no reply_to. */
    public function test_an_edit_keeps_the_quote(): void
    {
        [$batteur, $bassiste, $space, $channel] = $this->band();
        $answered = $this->message($channel, $bassiste, 'on répète samedi ?', '2026-09-10 20:00:00');
        $other = $this->message($channel, $bassiste, 'et dimanche ?', '2026-09-10 20:01:00');
        $reply = $this->message($channel, $batteur, 'oui', '2026-09-10 20:02:00', replyTo: $answered);

        $this->client->loginUser($batteur);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $space->id . '/chat/messages/' . $reply->id,
            ['content' => 'oui, à 14h', 'reply_to' => (string) $other->id],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $edited = self::getContainer()->get(MessageRepository::class)->find($reply->id);
        $this->assertInstanceOf(Message::class, $edited);
        $this->assertSame((string) $answered->id, (string) $edited->replyTo?->id);
        $this->assertNotNull($edited->updateDatetime);
        $this->assertJsonEquals(['@context' => '/api/contexts/ChatMessage'] + $this->expectedMessage(
            $edited,
            $space,
            $batteur,
            'oui, à 14h',
            editableContent: 'oui, à 14h',
            replyTo: $this->expectedQuote($answered, $bassiste, 'on répète samedi ?'),
            updateDatetime: $edited->updateDatetime->format('c'),
        ));
    }

    /**
     * @return array{User, User, BandSpace, MessageThread}
     */
    private function band(): array
    {
        $batteur = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $bassiste = UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $batteur])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $bassiste])->create();

        return [$batteur, $bassiste, $space, MessageThreadFactory::new()->forBandSpace($space)->create()];
    }

    private function message(
        MessageThread $channel,
        User $author,
        string $content,
        string $creationDatetime,
        ?Message $replyTo = null,
        bool $deleted = false,
        ?string $imageFileId = null,
        ?string $voiceNoteFileId = null,
    ): Message {
        return MessageFactory::new([
            'thread' => $channel,
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
    private function post(BandSpace $space, array $body): void
    {
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $space->id . '/chat/messages',
            $body,
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );
    }

    /** The one message of the channel that answers another. */
    private function replyIn(MessageThread $channel): Message
    {
        $replies = array_values(array_filter(
            self::getContainer()->get(MessageRepository::class)->findBy(['thread' => $channel->id]),
            static fn (Message $message): bool => $message->replyTo !== null,
        ));
        $this->assertCount(1, $replies);

        return $replies[0];
    }

    private function countMessagesIn(MessageThread $channel): int
    {
        return self::getContainer()->get(MessageRepository::class)->count(['thread' => $channel->id]);
    }

    /**
     * @return array{id: string, author_id: string, author_display_name: string, excerpt: string, has_image: bool, has_voice_note: bool, is_deleted: bool}
     */
    private function expectedQuote(Message $answered, User $author, string $excerpt): array
    {
        return [
            'id' => (string) $answered->id,
            'author_id' => (string) $author->id,
            'author_display_name' => $author->username,
            'excerpt' => $excerpt,
            'has_image' => false,
            'has_voice_note' => false,
            'is_deleted' => false,
        ];
    }

    /**
     * @param array<string, mixed>|null $replyTo
     * @param array<string, mixed>|null $image
     * @param array<string, mixed>|null $voiceNote
     *
     * @return array<string, mixed>
     */
    private function expectedMessage(
        Message $message,
        BandSpace $space,
        User $author,
        string $content,
        ?string $editableContent = null,
        ?array $replyTo = null,
        ?array $image = null,
        ?array $voiceNote = null,
        bool $isDeleted = false,
        ?string $updateDatetime = null,
    ): array {
        return [
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $author->id,
            'author_username' => $author->username,
            'author_display_name' => $author->username,
            'author_is_deleted' => false,
            'author_profile_picture_url' => null,
            'content' => $content,
            'creation_datetime' => $message->creationDatetime->format('c'),
            'reactions' => [],
            'attachments' => [],
            'update_datetime' => $updateDatetime,
            'editable_content' => $editableContent,
            'is_deleted' => $isDeleted,
            'is_pinned' => false,
            'pinned_datetime' => null,
            'pinned_by_username' => null,
            'pinned_by_display_name' => null,
            'read_by_usernames' => [],
            'read_by_display_names' => [],
            'read_count' => 0,
            'reply_to' => $replyTo,
            'image' => $image,
            'voice_note' => $voiceNote,
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
