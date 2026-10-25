<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Repository\Message\MessageRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Editing your own message in a direct conversation (#1245). One authenticated request per test.
 */
#[ResetDatabase]
class MessagePatchTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author, 'on répète mardi');

        $this->patch($thread, $message, 'on répète mercredi');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_the_author_edits_their_own_message(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author, 'on répète mardi');
        $messageId = (string) $message->id;

        $this->client->loginUser($author);
        $this->patch($thread, $message, "on répète mercredi\nà 20h");

        $this->assertResponseIsSuccessful();
        $edited = self::getContainer()->get(MessageRepository::class)->find($messageId);
        $this->assertNotNull($edited->updateDatetime);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $messageId,
            '@type' => 'Message',
            'id' => $messageId,
            'creation_datetime' => '2026-10-20T20:00:00+00:00',
            'author' => [
                '@id' => '/api/users/' . $author->id,
                '@type' => 'User',
                'id' => $author->id,
                'username' => 'alice_drums',
                'display_name' => 'alice_drums',
            ],
            'content' => "on répète mercredi<br />\nà 20h",
            'is_seen' => false,
            'update_datetime' => $edited->updateDatetime->format(\DateTimeInterface::ATOM),
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => "on répète mercredi\nà 20h",
        ]);
        $this->assertSame("on répète mercredi\nà 20h", $edited->content);
    }

    public function test_an_edit_keeps_its_seen_receipt(): void
    {
        $author = $this->user('alice_drums');
        $other = $this->user('bob_bass');
        $thread = $this->directThread($author, $other);
        $message = $this->message($thread, $author, 'on répète mardi');
        MessageThreadMetaFactory::new([
            'thread' => $thread,
            'user' => $other,
            'lastSeenDatetime' => new \DateTimeImmutable('2026-10-20 21:00:00'),
        ])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($author);
        $this->patch($thread, $message, 'on répète mercredi');

        $this->assertResponseIsSuccessful();
        $edited = self::getContainer()->get(MessageRepository::class)->find($messageId);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $messageId,
            '@type' => 'Message',
            'id' => $messageId,
            'creation_datetime' => '2026-10-20T20:00:00+00:00',
            'author' => [
                '@id' => '/api/users/' . $author->id,
                '@type' => 'User',
                'id' => $author->id,
                'username' => 'alice_drums',
                'display_name' => 'alice_drums',
            ],
            'content' => 'on répète mercredi',
            'is_seen' => true,
            'update_datetime' => $edited->updateDatetime->format(\DateTimeInterface::ATOM),
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => 'on répète mercredi',
        ]);
    }

    public function test_an_identical_body_is_not_an_edit(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author, 'on répète mardi');
        $messageId = (string) $message->id;
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($author);
        $this->patch($thread, $message, 'on répète mardi');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $messageId,
            '@type' => 'Message',
            'id' => $messageId,
            'creation_datetime' => '2026-10-20T20:00:00+00:00',
            'author' => [
                '@id' => '/api/users/' . $author->id,
                '@type' => 'User',
                'id' => $author->id,
                'username' => 'alice_drums',
                'display_name' => 'alice_drums',
            ],
            'content' => 'on répète mardi',
            'is_seen' => false,
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => 'on répète mardi',
        ]);
        $this->assertNull(self::getContainer()->get(MessageRepository::class)->find($messageId)->updateDatetime);
        $this->assertSame([], $hub->updates);
    }

    public function test_the_other_participant_cannot_edit_it(): void
    {
        $author = $this->user('alice_drums');
        $other = $this->user('bob_bass');
        $thread = $this->directThread($author, $other);
        $message = $this->message($thread, $author, 'on répète mardi');
        $messageId = (string) $message->id;

        $this->client->loginUser($other);
        $this->patch($thread, $message, 'on annule');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 403,
            'type' => '/errors/403',
            'detail' => "Seul l'auteur peut modifier ce message",
            'description' => "Seul l'auteur peut modifier ce message",
        ]);
        $this->assertSame('on répète mardi', self::getContainer()->get(MessageRepository::class)->find($messageId)->content);
    }

    public function test_a_deleted_message_cannot_be_edited(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = MessageFactory::new([
            'thread' => $thread,
            'author' => $author,
            'content' => '',
            'deletionDatetime' => new \DateTimeImmutable('2026-10-21 08:00:00'),
        ])->create();

        $this->client->loginUser($author);
        $this->patch($thread, $message, 'revenu');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Message introuvable',
            'description' => 'Message introuvable',
        ]);
    }

    public function test_a_voice_note_cannot_be_edited(): void
    {
        // Sent alone, so an edit could only bolt a caption onto it.
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = MessageFactory::new([
            'thread' => $thread,
            'author' => $author,
            'content' => '',
            'voiceNoteFileId' => '0199a3b4-0000-7000-8000-000000000001',
            'voiceNoteDurationSeconds' => 12,
        ])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($author);
        $this->patch($thread, $message, 'la légende');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 403,
            'type' => '/errors/403',
            'detail' => 'Une note vocale ne peut pas être modifiée',
            'description' => 'Une note vocale ne peut pas être modifiée',
        ]);
        $this->assertSame('', self::getContainer()->get(MessageRepository::class)->find($messageId)->content);
    }

    public function test_a_message_of_another_conversation_is_not_found(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $elsewhere = $this->directThread($author, $this->user('carla_keys'));
        $message = $this->message($elsewhere, $author, 'ailleurs');

        $this->client->loginUser($author);
        $this->patch($thread, $message, 'ici');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Message introuvable',
            'description' => 'Message introuvable',
        ]);
    }

    public function test_a_stranger_to_the_conversation_gets_a_404(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author, 'on répète mardi');

        $this->client->loginUser($this->user('intrus'));
        $this->patch($thread, $message, 'on annule');

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

    public function test_a_band_channel_is_refused_even_to_its_author(): void
    {
        $space = BandSpaceFactory::new()->create();
        $channel = MessageThreadFactory::new()->forBandSpace($space)->create();
        $member = $this->user('batteur');
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member, 'status' => MembershipStatus::Active])->create();
        $message = $this->message($channel, $member, 'salut');

        $this->client->loginUser($member);
        $this->patch($channel, $message, 'salut à tous');

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

    public function test_a_blocked_pair_cannot_edit(): void
    {
        $author = $this->user('alice_drums');
        $other = $this->user('bob_bass');
        UserBlockFactory::new()->create(['blocker' => $other, 'blocked' => $author]);
        $thread = $this->directThread($author, $other);
        $message = $this->message($thread, $author, 'on répète mardi');

        $this->client->loginUser($author);
        $this->patch($thread, $message, 'on répète mercredi');

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

    public function test_blank_content_is_refused(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author, 'on répète mardi');

        $this->client->loginUser($author);
        $this->patch($thread, $message, '');

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

    public function test_a_body_without_content_is_refused(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author, 'on répète mardi');

        $this->client->loginUser($author);
        $this->client->request(
            'PATCH',
            '/api/message_threads/' . $thread->id . '/messages/' . $message->id,
            server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
            content: '{}',
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

    public function test_the_author_still_edits_once_the_other_account_is_gone(): void
    {
        $author = $this->user('alice_drums');
        $deleted = UserFactory::new()->asBaseUser()->create([
            'username' => 'deleted_user',
            'email' => 'deleted@test.com',
            'deletionDatetime' => new \DateTimeImmutable('2026-10-21 08:00:00'),
        ]);
        $thread = $this->directThread($author, $deleted);
        $message = $this->message($thread, $author, 'on répète mardi');
        $messageId = (string) $message->id;

        $this->client->loginUser($author);
        $this->patch($thread, $message, 'on annule');

        $this->assertResponseIsSuccessful();
        $edited = self::getContainer()->get(MessageRepository::class)->find($messageId);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $messageId,
            '@type' => 'Message',
            'id' => $messageId,
            'creation_datetime' => '2026-10-20T20:00:00+00:00',
            'author' => [
                '@id' => '/api/users/' . $author->id,
                '@type' => 'User',
                'id' => $author->id,
                'username' => 'alice_drums',
                'display_name' => 'alice_drums',
            ],
            'content' => 'on annule',
            'is_seen' => false,
            'update_datetime' => $edited->updateDatetime->format(\DateTimeInterface::ATOM),
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => 'on annule',
        ]);
    }

    private function patch(MessageThread $thread, Message $message, string $content): void
    {
        $this->client->request(
            'PATCH',
            '/api/message_threads/' . $thread->id . '/messages/' . $message->id,
            server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
            content: json_encode(['content' => $content], JSON_THROW_ON_ERROR),
        );
    }

    private function user(string $username): User
    {
        return UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
    }

    private function directThread(User $first, User $second): MessageThread
    {
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $first])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $second])->create();

        return $thread;
    }

    private function message(MessageThread $thread, User $author, string $content): Message
    {
        return MessageFactory::new([
            'thread' => $thread,
            'author' => $author,
            'content' => $content,
            'creationDatetime' => new \DateTime('2026-10-20 20:00:00'),
        ])->create();
    }
}
