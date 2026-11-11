<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A window of a conversation anchored on one message (#1380), which is what a click on a quote lands
 * on when the answered message is above the loaded pages: the band chat's window (#1039) for two.
 */
#[ResetDatabase]
class MessageWindowTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $this->window('3f2504e0-4f89-41d3-9a0c-0305e82c3300', 'around', '3f2504e0-4f89-41d3-9a0c-0305e82c3301');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_around_lands_on_the_message_with_twenty_five_on_each_side(): void
    {
        [$me, $thread, $messages] = $this->conversationOf(60);

        $this->client->loginUser($me);
        $this->window((string) $thread->id, 'around', (string) $messages[30]->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->expectedWindow($thread, $me, array_slice($messages, 5, 51), hasOlder: true, hasNewer: true, total: 60));
    }

    public function test_around_the_newest_message_reaches_the_live_end(): void
    {
        [$me, $thread, $messages] = $this->conversationOf(30);

        $this->client->loginUser($me);
        $this->window((string) $thread->id, 'around', (string) $messages[29]->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->expectedWindow($thread, $me, array_slice($messages, 4, 26), hasOlder: true, hasNewer: false, total: 30));
    }

    public function test_before_reads_the_fifty_older_ones_without_the_anchor(): void
    {
        [$me, $thread, $messages] = $this->conversationOf(60);

        $this->client->loginUser($me);
        $this->window((string) $thread->id, 'before', (string) $messages[55]->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->expectedWindow($thread, $me, array_slice($messages, 5, 50), hasOlder: true, hasNewer: true, total: 60));
    }

    public function test_after_reads_the_newer_ones_up_to_the_live_end(): void
    {
        [$me, $thread, $messages] = $this->conversationOf(60);

        $this->client->loginUser($me);
        $this->window((string) $thread->id, 'after', (string) $messages[20]->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->expectedWindow($thread, $me, array_slice($messages, 21, 39), hasOlder: true, hasNewer: false, total: 60));
    }

    /** The bubbles read as they do in the list: the other person's, a reply with its quote, a tombstone. */
    public function test_the_window_carries_what_the_list_shows(): void
    {
        [$me, $thread] = $this->conversationOf(0);
        $lea = UserFactory::new()->asBaseUser()->create(['username' => 'lea', 'email' => 'lea@test.com']);
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $lea])->create();
        $answered = $this->message($thread, $lea, 'on se voit samedi ?', 0);
        $reply = $this->message($thread, $me, 'oui', 1, replyTo: $answered);
        $tombstone = $this->message($thread, $lea, '', 2, deleted: true);

        $this->client->loginUser($me);
        $this->window((string) $thread->id, 'around', (string) $answered->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageWindow',
            '@id' => '/api/message_threads/' . $thread->id . '/message_window',
            '@type' => 'MessageWindow',
            'thread_id' => (string) $thread->id,
            'messages' => [
                $this->expectedMessage($answered, $lea, 'on se voit samedi ?'),
                $this->expectedMessage($reply, $me, 'oui') + [
                    'editable_content' => 'oui',
                    'reply_to' => [
                        'id' => (string) $answered->id,
                        'author_id' => (string) $lea->id,
                        'author_display_name' => 'lea',
                        'excerpt' => 'on se voit samedi ?',
                        'has_image' => false,
                        'has_voice_note' => false,
                        'is_deleted' => false,
                    ],
                ],
                ['is_deleted' => true] + $this->expectedMessage($tombstone, $lea, ''),
            ],
            'has_older' => false,
            'has_newer' => false,
            'total_items' => 3,
        ]);
    }

    public function test_no_anchor_is_refused(): void
    {
        [$me, $thread] = $this->conversationOf(1);

        $this->client->loginUser($me);
        $this->client->jsonRequest('GET', '/api/message_threads/' . $thread->id . '/message_window', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertBadRequest();
    }

    public function test_two_anchors_are_refused(): void
    {
        [$me, $thread, $messages] = $this->conversationOf(2);

        $this->client->loginUser($me);
        $this->client->jsonRequest(
            'GET',
            '/api/message_threads/' . $thread->id . '/message_window?around=' . $messages[0]->id . '&after=' . $messages[1]->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertBadRequest();
    }

    public function test_an_anchor_that_is_not_a_uuid_is_refused_on_its_parameter(): void
    {
        [$me, $thread] = $this->conversationOf(1);

        $this->client->loginUser($me);
        $this->window((string) $thread->id, 'before', 'pas-un-uuid');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/51120b12-a2bc-41bf-aa53-cd73daf330d0',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'before',
                    'message' => 'Cette valeur n\'est pas un UUID valide.',
                    'code' => '51120b12-a2bc-41bf-aa53-cd73daf330d0',
                ],
            ],
            'detail' => 'before: Cette valeur n\'est pas un UUID valide.',
            'type' => '/validation_errors/51120b12-a2bc-41bf-aa53-cd73daf330d0',
            'title' => 'An error occurred',
            'description' => 'before: Cette valeur n\'est pas un UUID valide.',
        ]);
    }

    /** A message of another conversation cannot anchor a window here, nor tell whether it exists. */
    public function test_a_message_of_another_conversation_is_not_found(): void
    {
        [$me, $thread] = $this->conversationOf(1);
        [, , $elsewhere] = $this->conversationOf(1, username: 'autre', email: 'autre@test.com');

        $this->client->loginUser($me);
        $this->window((string) $thread->id, 'around', (string) $elsewhere[0]->id);

        $this->assertNotFound('Message introuvable');
    }

    /** 404 rather than 403, as the list: a 403 would confirm the conversation exists. */
    public function test_someone_outside_the_conversation_cannot_read_a_window(): void
    {
        [, $thread, $messages] = $this->conversationOf(1);
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);

        $this->client->loginUser($outsider);
        $this->window((string) $thread->id, 'around', (string) $messages[0]->id);

        $this->assertNotFound('Thread not found.');
    }

    /** A band's channel is read through the band chat's own window, never this one. */
    public function test_a_band_channel_is_not_found(): void
    {
        $me = UserFactory::new()->asBaseUser()->create(['username' => 'moi', 'email' => 'moi@test.com']);
        $channel = MessageThreadFactory::new()->forBandSpace(BandSpaceFactory::new()->create())->create();
        $message = $this->message($channel, $me, 'salut', 0);

        $this->client->loginUser($me);
        $this->window((string) $channel->id, 'around', (string) $message->id);

        $this->assertNotFound('Thread not found.');
    }

    private function assertBadRequest(): void
    {
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Indique un seul message de référence : around, before ou after',
            'status' => 400,
            'type' => '/errors/400',
            'description' => 'Indique un seul message de référence : around, before ou after',
        ]);
    }

    private function assertNotFound(string $detail): void
    {
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => 404,
            'type' => '/errors/404',
            'description' => $detail,
        ]);
    }

    private function window(string $threadId, string $direction, string $anchorId): void
    {
        $this->client->jsonRequest(
            'GET',
            '/api/message_threads/' . $threadId . '/message_window?' . $direction . '=' . $anchorId,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );
    }

    /**
     * A participant and a conversation of `$count` of their messages one minute apart, oldest first.
     *
     * @return array{User, MessageThread, list<Message>}
     */
    private function conversationOf(int $count, string $username = 'moi', string $email = 'moi@test.com'): array
    {
        $me = UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $email]);
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $me])->create();

        $messages = [];
        for ($index = 0; $index < $count; ++$index) {
            $messages[] = $this->message($thread, $me, 'message ' . $index, $index);
        }

        return [$me, $thread, $messages];
    }

    private function message(MessageThread $thread, User $author, string $content, int $minute, ?Message $replyTo = null, bool $deleted = false): Message
    {
        $creationDatetime = (new \DateTime('2026-10-20 10:00:00'))->modify('+' . $minute . ' minutes');

        return MessageFactory::new([
            'thread' => $thread,
            'author' => $author,
            'content' => $content,
            'creationDatetime' => $creationDatetime,
            'replyTo' => $replyTo,
            'deletionDatetime' => $deleted ? \DateTimeImmutable::createFromMutable($creationDatetime) : null,
        ])->create();
    }

    /**
     * @param list<Message> $messages all written by `$author`, so each carries its editable content
     *
     * @return array<string, mixed>
     */
    private function expectedWindow(MessageThread $thread, User $author, array $messages, bool $hasOlder, bool $hasNewer, int $total): array
    {
        return [
            '@context' => '/api/contexts/MessageWindow',
            '@id' => '/api/message_threads/' . $thread->id . '/message_window',
            '@type' => 'MessageWindow',
            'thread_id' => (string) $thread->id,
            'messages' => array_map(
                fn (Message $message): array => $this->expectedMessage($message, $author, $message->content) + ['editable_content' => $message->content],
                $messages,
            ),
            'has_older' => $hasOlder,
            'has_newer' => $hasNewer,
            'total_items' => $total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedMessage(Message $message, User $author, string $content): array
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
        ];
    }
}
