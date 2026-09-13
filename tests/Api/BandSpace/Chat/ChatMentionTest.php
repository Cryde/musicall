<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\Message\MessageMentionRepository;
use App\Repository\Message\MessageRepository;
use App\Repository\Notification\NotificationRepository;
use App\Service\Notification\NotificationCreator;
use App\Tests\ApiTestCase;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageMentionFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * @-mentions in a Band Space channel (#964).
 *
 * A mention is the only thing in the chat that writes a Notification row, so these also pin that an
 * ordinary message still writes none, which is concern 10 of #948.
 */
#[ResetDatabase]
class ChatMentionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_mentioning_a_member_notifies_them_and_not_the_author(): void
    {
        [$space, , $sender, $others, $kicked] = $this->band();
        $alice = $others[0];

        $this->client->loginUser($sender);
        $this->post($space, 'on répète mardi @[' . $alice->id . ']');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $notifications = self::getContainer()->get(NotificationRepository::class);
        $forAlice = $notifications->findForRecipient($alice, 10, 0);
        $this->assertCount(1, $forAlice);
        $this->assertSame(NotificationType::BandSpaceChatMention, $forAlice[0]->type);
        $this->assertSame(
            [
                'band_space_id' => (string) $space->id,
                'band_space_name' => 'Les Trois Accords',
                'message_id' => $this->lastMessageId(),
                'actor_id' => (string) $sender->id,
                'actor_username' => 'batteur',
            ],
            $forAlice[0]->payload,
        );

        $this->assertCount(0, $notifications->findForRecipient($sender, 10, 0), 'The author is never notified');
        $this->assertCount(0, $notifications->findForRecipient($others[1], 10, 0), 'Nobody else was named');
        $this->assertCount(0, $notifications->findForRecipient($kicked, 10, 0));
    }

    public function test_an_ordinary_message_notifies_nobody(): void
    {
        // The rule the whole epic rests on: unread is a count, not a notification.
        [$space, , $sender] = $this->band();

        $this->client->loginUser($sender);
        $this->post($space, 'on répète mardi');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findAll());
    }

    public function test_mentioning_yourself_notifies_nobody(): void
    {
        [$space, , $sender] = $this->band();

        $this->client->loginUser($sender);
        $this->post($space, 'note pour moi @[' . $sender->id . ']');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findAll());
    }

    public function test_mentioning_a_former_member_notifies_nobody(): void
    {
        // Concern 1 of #948. The id is well formed and was a member yesterday; the resolver is what
        // refuses it, through findActiveBandSpaceMembersByIds.
        [$space, , $sender, , $kicked] = $this->band();

        $this->client->loginUser($sender);
        $this->post($space, 'salut @[' . $kicked->id . ']');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findAll());
    }

    public function test_tous_notifies_every_active_member_except_the_author(): void
    {
        [$space, , $sender, $others, $kicked] = $this->band();

        $this->client->loginUser($sender);
        $this->post($space, '@[tous] répète annulée demain');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $notifications = self::getContainer()->get(NotificationRepository::class);
        $this->assertCount(1, $notifications->findForRecipient($others[0], 10, 0));
        $this->assertCount(1, $notifications->findForRecipient($others[1], 10, 0));
        $this->assertCount(0, $notifications->findForRecipient($sender, 10, 0), 'Sending @tous is not mentioning yourself');
        $this->assertCount(0, $notifications->findForRecipient($kicked, 10, 0), 'Everyone means the current band');
        $this->assertCount(2, $notifications->findAll());
    }

    public function test_the_mentioned_members_are_recorded_on_the_message(): void
    {
        // The rows the renderer reads. Written for @tous too, so "messages that mention me" is one
        // predicate however the sender wrote it.
        [$space, , $sender, $others, $kicked] = $this->band();

        $this->client->loginUser($sender);
        $this->post($space, '@[tous] on répète mardi');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $mentions = self::getContainer()->get(MessageMentionRepository::class)->findAll();
        $mentionedIds = array_map(static fn ($mention): string => (string) $mention->mentionedUser->id, $mentions);
        sort($mentionedIds);

        $expected = [(string) $sender->id, (string) $others[0]->id, (string) $others[1]->id];
        sort($expected);

        // The author included: they are in the band, and the row is about who was named rather than
        // who was told. Only the notification excludes them.
        $this->assertSame($expected, $mentionedIds);
        $this->assertNotContains((string) $kicked->id, $mentionedIds);
    }

    public function test_a_mention_renders_as_a_styled_name_not_as_its_raw_token(): void
    {
        [$space, , $sender, $others] = $this->band();
        $alice = $others[0];

        $this->client->loginUser($sender);
        $this->post($space, 'salut @[' . $alice->id . ']');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $content = $this->getResponseAsArray()['content'];
        $this->assertSame('salut <span class="chat-mention">@bassiste</span>', $content);
    }

    public function test_a_mention_still_names_a_member_who_has_since_left_the_band(): void
    {
        // The reason the mentions are a table rather than something re-derived at render time.
        // Resolving against the active roster would print `@inconnu` over a real name in history, and
        // a chat log is exactly where that is wrong. Seeded rather than posted, because the member has
        // to be mentioned while a member and read after leaving, which is two writes and one request.
        [$space, $channel, $sender, $others] = $this->band();
        $alice = $others[0];

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $message = MessageFactory::new(['thread' => $channel, 'author' => $sender])->create([
            'content' => 'salut @[' . $alice->id . ']',
        ]);
        MessageMentionFactory::new(['message' => $message, 'mentionedUser' => $alice])->create();

        $membership = self::getContainer()->get(BandSpaceMembershipRepository::class)
            ->findOneBy(['bandSpace' => $space->id, 'user' => $alice->id]);
        $membership->status = MembershipStatus::Left;
        $entityManager->flush();

        $this->client->loginUser($sender);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseIsSuccessful();

        $this->assertSame(
            'salut <span class="chat-mention">@bassiste</span>',
            $this->getResponseAsArray()['member'][0]['content'],
        );
    }

    public function test_each_message_on_a_page_carries_only_its_own_mention(): void
    {
        // The page is built from one batched lookup keyed by message id, so an off-by-one there would
        // put one member's name on somebody else's sentence and every single-message test would still
        // pass.
        [$space, $channel, $sender, $others] = $this->band();

        $first = MessageFactory::new(['thread' => $channel, 'author' => $sender])->create([
            'content' => 'salut @[' . $others[0]->id . ']',
            'creationDatetime' => new \DateTime('2026-09-01 10:00:00'),
        ]);
        MessageMentionFactory::new(['message' => $first, 'mentionedUser' => $others[0]])->create();

        $second = MessageFactory::new(['thread' => $channel, 'author' => $sender])->create([
            'content' => 'et toi @[' . $others[1]->id . ']',
            'creationDatetime' => new \DateTime('2026-09-01 11:00:00'),
        ]);
        MessageMentionFactory::new(['message' => $second, 'mentionedUser' => $others[1]])->create();

        MessageFactory::new(['thread' => $channel, 'author' => $sender])->create([
            'content' => 'personne ici',
            'creationDatetime' => new \DateTime('2026-09-01 12:00:00'),
        ]);

        $this->client->loginUser($sender);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseIsSuccessful();

        // Newest first, which is how the collection answers.
        $contents = array_column($this->getResponseAsArray()['member'], 'content');
        $this->assertSame(
            [
                'personne ici',
                'et toi <span class="chat-mention">@chanteuse</span>',
                'salut <span class="chat-mention">@bassiste</span>',
            ],
            $contents,
        );
    }

    public function test_a_mentioned_member_whose_account_is_gone_is_not_named(): void
    {
        // Same rule the author already follows: DeleteAccountProcedure rewrites the handle to
        // `deleted_<uuid>`, and that must never reach a reader.
        [$space, $channel, $sender, $others] = $this->band();
        $gone = $others[0];

        $message = MessageFactory::new(['thread' => $channel, 'author' => $sender])->create([
            'content' => 'salut @[' . $gone->id . ']',
        ]);
        MessageMentionFactory::new(['message' => $message, 'mentionedUser' => $gone])->create();

        $gone->username = 'deleted_3f2504e0';
        $gone->deletionDatetime = new \DateTimeImmutable('2026-09-01 10:00:00');
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->loginUser($sender);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseIsSuccessful();

        $this->assertSame(
            'salut <span class="chat-mention">@Utilisateur supprimé</span>',
            $this->getResponseAsArray()['member'][0]['content'],
        );
    }

    public function test_a_notification_failure_does_not_break_the_message(): void
    {
        // Epic #689 contract: the message is committed before anything is dispatched, and the listener
        // swallows its own failures, so a broken notification path costs a notification and nothing more.
        [$space, $channel, $sender, $others] = $this->band();
        self::getContainer()->set(NotificationCreator::class, $this->throwingNotificationCreator());

        $this->client->loginUser($sender);
        $this->post($space, 'salut @[' . $others[0]->id . ']');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findAll());
        $this->assertNotNull(
            self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]),
            'The message must survive a dead notification path',
        );
    }

    private function throwingNotificationCreator(): NotificationCreator
    {
        return new readonly class extends NotificationCreator {
            public function __construct()
            {
            }

            public function create(User $recipient, NotificationType $type, array $payload): void
            {
                throw new \RuntimeException('Notification creation failed');
            }

            public function createForRecipients(iterable $recipients, NotificationType $type, array $payload): void
            {
                throw new \RuntimeException('Notification creation failed');
            }
        };
    }

    /**
     * Three active members, one kicked.
     *
     * @return array{0: BandSpace, 1: MessageThread, 2: User, 3: User[], 4: User}
     */
    private function band(): array
    {
        $space = BandSpaceFactory::new()->create(['name' => 'Les Trois Accords']);

        $sender = $this->member($space, 'batteur');
        $others = [$this->member($space, 'bassiste'), $this->member($space, 'chanteuse')];
        $kicked = $this->member($space, 'ancien', MembershipStatus::Kicked);

        return [$space, MessageThreadFactory::new()->forBandSpace($space)->create(), $sender, $others, $kicked];
    }

    private function member(
        BandSpace $bandSpace,
        string $username,
        MembershipStatus $status = MembershipStatus::Active,
    ): User {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => $username,
            'email' => $username . '@test.com',
        ]);
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => $status,
        ])->create();

        return $user;
    }

    private function post(BandSpace $bandSpace, string $content): void
    {
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/chat/messages',
            ['content' => $content],
            self::HEADERS,
        );
    }

    private function lastMessageId(): ?string
    {
        return $this->getResponseAsArray()['id'] ?? null;
    }
}
