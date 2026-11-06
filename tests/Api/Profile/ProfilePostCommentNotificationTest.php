<?php

declare(strict_types=1);

namespace App\Tests\Api\Profile;

use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Enum\Notification\PushCategory;
use App\Event\ProfilePostCommentedEvent;
use App\EventSubscriber\ProfilePostCommentedListener;
use App\Messenger\SendPushNotification;
use App\Repository\Notification\NotificationRepository;
use App\Repository\Profile\ProfilePostCommentRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Notification\NotificationFactory;
use App\Tests\Factory\Profile\ProfilePostCommentFactory;
use App\Tests\Factory\Profile\ProfilePostFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The bell after a comment on a wall post, or a reply (#1316). */
#[ResetDatabase]
class ProfilePostCommentNotificationTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_comment_tells_the_post_s_author(): void
    {
        $owner = $this->member('julien');
        $chloe = $this->member('chloe');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);

        $this->comment($chloe, (string) $post->id);

        $comment = $this->onlyCommentOf($chloe);
        $this->assertNotified($owner, NotificationType::ProfilePostComment, [
            'post_id' => (string) $post->id,
            'comment_id' => (string) $comment->id,
            'wall_username' => 'julien',
            'actor_id' => (string) $chloe->id,
            'actor_username' => 'chloe',
        ]);
        $this->assertNotNotified($chloe);

        $pushes = $this->queuedPushes();
        $this->assertCount(1, $pushes);
        $this->assertSame((string) $owner->id, $pushes[0]->userId);
        $this->assertSame(PushCategory::PublicationComment, $pushes[0]->category);
        $this->assertSame('chloe a commenté ton post', $pushes[0]->body);
        $this->assertSame(['type' => 'profile_post_comment'], $pushes[0]->data);
    }

    public function test_a_reply_tells_the_comment_s_author_and_the_post_s_author(): void
    {
        $owner = $this->member('julien');
        $chloe = $this->member('chloe');
        $marc = $this->member('marc');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $parent = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $chloe]);

        $this->comment($marc, (string) $post->id, (string) $parent->id);

        $reply = $this->onlyCommentOf($marc);
        $payload = [
            'post_id' => (string) $post->id,
            'comment_id' => (string) $reply->id,
            'wall_username' => 'julien',
            'actor_id' => (string) $marc->id,
            'actor_username' => 'marc',
        ];
        $this->assertNotified($chloe, NotificationType::ProfilePostCommentReply, $payload);
        $this->assertNotified($owner, NotificationType::ProfilePostComment, $payload);
        $this->assertNotNotified($marc);
    }

    public function test_a_reply_to_the_owner_s_own_comment_tells_them_once(): void
    {
        $owner = $this->member('julien');
        $chloe = $this->member('chloe');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $parent = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $owner]);

        $this->comment($chloe, (string) $post->id, (string) $parent->id);

        $reply = $this->onlyCommentOf($chloe);
        $this->assertNotified($owner, NotificationType::ProfilePostCommentReply, [
            'post_id' => (string) $post->id,
            'comment_id' => (string) $reply->id,
            'wall_username' => 'julien',
            'actor_id' => (string) $chloe->id,
            'actor_username' => 'chloe',
        ]);
    }

    public function test_commenting_your_own_post_tells_nobody(): void
    {
        $owner = $this->member('julien');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);

        $this->comment($owner, (string) $post->id);

        $this->assertNotNotified($owner);
        $this->assertSame([], $this->queuedPushes());
    }

    public function test_nobody_blocked_either_way_with_the_commenter_hears_of_it(): void
    {
        // The API already refuses these comments with a 404; the listener holds the line on its own too.
        $owner = $this->member('julien');
        $chloe = $this->member('chloe');
        $marc = $this->member('marc');
        UserBlockFactory::new()->create(['blocker' => $owner, 'blocked' => $marc]);
        UserBlockFactory::new()->create(['blocker' => $marc, 'blocked' => $chloe]);
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $parent = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $chloe]);
        $reply = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $marc, 'parent' => $parent]);

        self::getContainer()->get(ProfilePostCommentedListener::class)(new ProfilePostCommentedEvent($reply));

        $this->assertNotNotified($owner);
        $this->assertNotNotified($chloe);
    }

    public function test_a_closed_account_hears_nothing(): void
    {
        $owner = $this->member('julien');
        $gone = $this->member('deleted_123');
        $gone->deletionDatetime = new \DateTimeImmutable('2026-11-03T08:00:00+00:00');
        \Zenstruck\Foundry\Persistence\save($gone);
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $parent = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $gone]);

        $this->comment($owner, (string) $post->id, (string) $parent->id);

        $this->assertNotNotified($gone);
        $this->assertNotNotified($owner);
    }

    public function test_the_bell_follows_a_wall_owner_s_new_username(): void
    {
        $owner = $this->member('julien_roche');
        $reader = $this->member('chloe');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $notification = NotificationFactory::new([
            'recipient' => $reader,
            'type' => NotificationType::ProfilePostCommentReply,
            'payload' => [
                'post_id' => (string) $post->id,
                'comment_id' => 'comment-1',
                'wall_username' => 'julien',
                'actor_id' => 'actor-1',
                'actor_username' => 'marc',
            ],
            'creationDatetime' => new \DateTimeImmutable('2026-11-02 10:00:00'),
        ])->create();

        $this->client->loginUser($reader);
        $this->client->jsonRequest('GET', '/api/user/notifications', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotification',
            '@id' => '/api/user/notifications',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/user/notifications/' . $notification->id,
                    '@type' => 'UserNotification',
                    'id' => (string) $notification->id,
                    'type' => 'profile_post_comment_reply',
                    'payload' => [
                        'post_id' => (string) $post->id,
                        'comment_id' => 'comment-1',
                        'wall_username' => 'julien_roche',
                        'actor_id' => 'actor-1',
                        'actor_username' => 'marc',
                    ],
                    'read_datetime' => null,
                    'creation_datetime' => $notification->creationDatetime->format(\DATE_ATOM),
                ],
            ],
        ]);
    }

    private function comment(User $author, string $postId, ?string $parentId = null): void
    {
        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $postId . '/comments', [
            'content' => 'Bravo',
            'parent_id' => $parentId,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * @param array<string, string> $payload
     */
    private function assertNotified(User $recipient, NotificationType $type, array $payload): void
    {
        $notifications = self::getContainer()->get(NotificationRepository::class)->findForRecipient($recipient, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame($type, $notifications[0]->type);
        $this->assertSame($payload, $notifications[0]->payload);
    }

    private function assertNotNotified(User $user): void
    {
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($user, 10, 0));
    }

    private function onlyCommentOf(User $author): ProfilePostComment
    {
        $comments = self::getContainer()->get(ProfilePostCommentRepository::class)->findBy(['author' => $author->id]);
        $this->assertCount(1, $comments);

        return $comments[0];
    }

    /** @return list<SendPushNotification> */
    private function queuedPushes(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        \assert($transport instanceof InMemoryTransport);

        return array_values(array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $transport->getSent()));
    }

    private function member(string $username): User
    {
        return UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
    }
}
