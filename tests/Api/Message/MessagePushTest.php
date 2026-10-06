<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\User\UserNotificationPreference;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\Notification\PushCategory;
use App\Messenger\SendPushNotification;
use App\Repository\Message\MessageRepository;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Every message reaches the other members' phones (#1110), with a preview of it. The response bodies
 * are covered by the send tests; only what was queued matters here.
 */
#[ResetDatabase]
class MessagePushTest extends ApiTestCase
{
    public function test_a_direct_message_is_pushed_to_the_recipient_only(): void
    {
        $sender = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice@example.com']);
        $recipient = UserFactory::new()->asBaseUser()->create(['username' => 'bob_bass', 'email' => 'bob@example.com']);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'On répète mardi ?',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['author' => $sender]);
        $this->assertNotNull($message);
        $this->assertEquals([
            new SendPushNotification(
                (string) $recipient->id,
                'alice_drums',
                'On répète mardi ?',
                ['type' => 'message', 'route' => '/messages/' . $message->thread->id],
                PushCategory::MessageReceived,
            ),
        ], $this->queuedPushes());
    }

    /**
     * The author gets nothing, a member who left gets nothing, and a member the message mentions gets
     * the mention push instead of the chat one.
     */
    public function test_a_band_message_is_pushed_to_the_other_members(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice@example.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'bob_bass', 'email' => 'bob@example.com']);
        $mentioned = UserFactory::new()->asBaseUser()->create(['username' => 'carl_keys', 'email' => 'carl@example.com']);
        $formerMember = UserFactory::new()->asBaseUser()->create(['username' => 'dora_sax', 'email' => 'dora@example.com']);
        $band = BandSpaceFactory::new()->create(['name' => 'Les Cactus']);
        foreach ([$author, $member, $mentioned] as $user) {
            BandSpaceMembershipFactory::new(['bandSpace' => $band, 'user' => $user])->create();
        }
        BandSpaceMembershipFactory::new(['bandSpace' => $band, 'user' => $formerMember, 'status' => MembershipStatus::Left])->create();
        MessageThreadFactory::new()->forBandSpace($band)->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $band->id . '/chat/messages', [
            'content' => 'Salut @[' . $mentioned->id . '] !',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['author' => $author]);
        $this->assertNotNull($message);
        $this->assertEquals([
            new SendPushNotification(
                (string) $member->id,
                'Les Cactus',
                'alice_drums : Salut @carl_keys !',
                ['type' => 'band_space_message', 'route' => '/band/' . $band->id . '/chat?message=' . $message->id],
                PushCategory::BandChat,
            ),
            new SendPushNotification(
                (string) $mentioned->id,
                'Les Cactus',
                'alice_drums vous a mentionné dans la discussion de « Les Cactus »',
                ['type' => 'band_space_chat_mention', 'route' => '/band/' . $band->id . '/chat?message=' . $message->id],
                PushCategory::BandMention,
            ),
        ], $this->queuedPushes());
    }

    /** Somebody who turned mention pushes off still hears of the message, as a chat push. */
    public function test_a_mentioned_member_without_mention_pushes_gets_the_chat_push(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice@example.com']);
        $mentioned = UserFactory::new()->asBaseUser()->create(['username' => 'carl_keys', 'email' => 'carl@example.com']);
        $preference = new UserNotificationPreference();
        $preference->user = $mentioned;
        $preference->pushBandMention = false;
        $mentioned->notificationPreference = $preference;
        \Zenstruck\Foundry\Persistence\save($preference);
        $band = BandSpaceFactory::new()->create(['name' => 'Les Cactus']);
        foreach ([$author, $mentioned] as $user) {
            BandSpaceMembershipFactory::new(['bandSpace' => $band, 'user' => $user])->create();
        }
        MessageThreadFactory::new()->forBandSpace($band)->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $band->id . '/chat/messages', [
            'content' => 'Salut @[' . $mentioned->id . '] !',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['author' => $author]);
        $this->assertNotNull($message);
        $route = '/band/' . $band->id . '/chat?message=' . $message->id;
        // The mention push is still queued; the worker drops it on the preference.
        $this->assertEquals([
            new SendPushNotification((string) $mentioned->id, 'Les Cactus', 'alice_drums : Salut @carl_keys !', ['type' => 'band_space_message', 'route' => $route], PushCategory::BandChat),
            new SendPushNotification((string) $mentioned->id, 'Les Cactus', 'alice_drums vous a mentionné dans la discussion de « Les Cactus »', ['type' => 'band_space_chat_mention', 'route' => $route], PushCategory::BandMention),
        ], $this->queuedPushes());
    }

    /** @return list<object> */
    private function queuedPushes(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        \assert($transport instanceof InMemoryTransport);

        return array_values(array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $transport->getSent()));
    }
}
