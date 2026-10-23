<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Entity\User\UserNotificationPreference;
use App\Service\Procedure\Message\MessageSenderProcedure;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * « Vu » in a direct conversation (#1243): a message the caller wrote, reached by the other person's
 * seen position, which only moves forward.
 */
#[ResetDatabase]
class MessageReadReceiptTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_only_your_own_messages_the_other_person_has_reached_are_seen(): void
    {
        $me = $this->user('alice_drums');
        $other = $this->user('bob_bass');
        $thread = $this->directThread($me, $other);
        $mine = $this->message($thread, $me, 'tu es dispo samedi ?', '2026-10-20 10:00:00');
        $theirs = $this->message($thread, $other, 'oui', '2026-10-20 10:05:00');
        // Written in the very second the other person read: one-second precision counts it as seen.
        $sameSecond = $this->message($thread, $me, 'super', '2026-10-20 10:10:00');
        $after = $this->message($thread, $me, 'à samedi', '2026-10-20 10:15:00');
        // Marked unread since: the read position is gone, the seen position stays.
        $this->positions($thread, $other, read: null, seen: '2026-10-20 10:10:00');

        $this->client->loginUser($me);
        $this->client->request('GET', '/api/messages/' . $thread->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $thread->id,
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/messages/' . $mine->id,
                    '@type' => 'Message',
                    'creation_datetime' => $mine->creationDatetime->format(\DateTimeInterface::ATOM),
                    'author' => [
                        '@id' => '/api/users/' . $me->id,
                        '@type' => 'User',
                        'id' => $me->id,
                        'username' => $me->username,
                        'display_name' => $me->username,
                    ],
                    'content' => 'tu es dispo samedi ?',
                    'is_seen' => true,
                ],
                [
                    '@id' => '/api/messages/' . $theirs->id,
                    '@type' => 'Message',
                    'creation_datetime' => $theirs->creationDatetime->format(\DateTimeInterface::ATOM),
                    'author' => [
                        '@id' => '/api/users/' . $other->id,
                        '@type' => 'User',
                        'id' => $other->id,
                        'username' => $other->username,
                        'display_name' => $other->username,
                    ],
                    'content' => 'oui',
                    'is_seen' => false,
                ],
                [
                    '@id' => '/api/messages/' . $sameSecond->id,
                    '@type' => 'Message',
                    'creation_datetime' => $sameSecond->creationDatetime->format(\DateTimeInterface::ATOM),
                    'author' => [
                        '@id' => '/api/users/' . $me->id,
                        '@type' => 'User',
                        'id' => $me->id,
                        'username' => $me->username,
                        'display_name' => $me->username,
                    ],
                    'content' => 'super',
                    'is_seen' => true,
                ],
                [
                    '@id' => '/api/messages/' . $after->id,
                    '@type' => 'Message',
                    'creation_datetime' => $after->creationDatetime->format(\DateTimeInterface::ATOM),
                    'author' => [
                        '@id' => '/api/users/' . $me->id,
                        '@type' => 'User',
                        'id' => $me->id,
                        'username' => $me->username,
                        'display_name' => $me->username,
                    ],
                    'content' => 'à samedi',
                    'is_seen' => false,
                ],
            ],
            'totalItems' => 4,
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

    public function test_replying_is_seeing_what_came_before(): void
    {
        $me = $this->user('alice_drums');
        $other = $this->user('bob_bass');
        $thread = $this->directThread($me, $other);
        $mine = $this->message($thread, $me, 'tu es dispo samedi ?', '2026-10-20 10:00:00');
        $this->positions($thread, $me, read: '2026-10-20 10:00:00', seen: '2026-10-20 10:00:00');
        $this->positions($thread, $other, read: null, seen: null);
        $reply = self::getContainer()->get(MessageSenderProcedure::class)->processByThread($thread, $other, 'oui');

        $this->client->loginUser($me);
        $this->client->request('GET', '/api/messages/' . $thread->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $thread->id,
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/messages/' . $mine->id,
                    '@type' => 'Message',
                    'creation_datetime' => $mine->creationDatetime->format(\DateTimeInterface::ATOM),
                    'author' => [
                        '@id' => '/api/users/' . $me->id,
                        '@type' => 'User',
                        'id' => $me->id,
                        'username' => $me->username,
                        'display_name' => $me->username,
                    ],
                    'content' => 'tu es dispo samedi ?',
                    'is_seen' => true,
                ],
                [
                    '@id' => '/api/messages/' . $reply->id,
                    '@type' => 'Message',
                    'creation_datetime' => $reply->creationDatetime->format(\DateTimeInterface::ATOM),
                    'author' => [
                        '@id' => '/api/users/' . $other->id,
                        '@type' => 'User',
                        'id' => $other->id,
                        'username' => $other->username,
                        'display_name' => $other->username,
                    ],
                    'content' => 'oui',
                    'is_seen' => false,
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

    public function test_a_reply_written_while_hiding_receipts_stays_private(): void
    {
        $me = $this->user('alice_drums');
        $other = $this->user('bob_bass');
        $thread = $this->directThread($me, $other);
        $mine = $this->message($thread, $me, 'tu es dispo samedi ?', '2026-10-20 10:00:00');
        $this->positions($thread, $me, read: '2026-10-20 10:00:00', seen: '2026-10-20 10:00:00');
        $this->positions($thread, $other, read: null, seen: null);
        $this->hideReceipts($other);
        $reply = self::getContainer()->get(MessageSenderProcedure::class)->processByThread($thread, $other, 'oui');
        // Turned back on afterwards: the reply, written while hidden, must not reveal what it answered.
        $other->notificationPreference->showReadReceipts = true;
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->loginUser($me);
        $this->client->request('GET', '/api/messages/' . $thread->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $thread->id,
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/messages/' . $mine->id,
                    '@type' => 'Message',
                    'creation_datetime' => $mine->creationDatetime->format(\DateTimeInterface::ATOM),
                    'author' => [
                        '@id' => '/api/users/' . $me->id,
                        '@type' => 'User',
                        'id' => $me->id,
                        'username' => $me->username,
                        'display_name' => $me->username,
                    ],
                    'content' => 'tu es dispo samedi ?',
                    'is_seen' => false,
                ],
                [
                    '@id' => '/api/messages/' . $reply->id,
                    '@type' => 'Message',
                    'creation_datetime' => $reply->creationDatetime->format(\DateTimeInterface::ATOM),
                    'author' => [
                        '@id' => '/api/users/' . $other->id,
                        '@type' => 'User',
                        'id' => $other->id,
                        'username' => $other->username,
                        'display_name' => $other->username,
                    ],
                    'content' => 'oui',
                    'is_seen' => false,
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

    public function test_the_receipt_loads_no_profile_of_its_own(): void
    {
        // A page holding only the caller's messages hydrates nobody else as an author, so the other
        // person, needed for the policy, would come alone with their three eager profile rows (#730).
        $me = $this->user('alice_drums');
        $other = $this->user('bob_bass');
        $thread = $this->directThread($me, $other);
        $mine = $this->message($thread, $me, 'tu es dispo samedi ?', '2026-10-20 10:00:00');
        $this->positions($thread, $other, read: null, seen: '2026-10-20 11:00:00');

        $this->client->loginUser($me);
        $this->client->enableProfiler();
        self::getContainer()->get('doctrine')->getManager()->clear();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->request('GET', '/api/messages/' . $thread->id);

        $this->assertResponseIsSuccessful();
        foreach (['user_musician_profile', 'user_teacher_profile', 'user_notification_preference'] as $table) {
            $this->assertNoQueryReadsTable($table, 'The other person must come with the thread, never in a query of their own');
        }
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $thread->id,
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/messages/' . $mine->id,
                    '@type' => 'Message',
                    'creation_datetime' => $mine->creationDatetime->format(\DateTimeInterface::ATOM),
                    'author' => [
                        '@id' => '/api/users/' . $me->id,
                        '@type' => 'User',
                        'id' => $me->id,
                        'username' => $me->username,
                        'display_name' => $me->username,
                    ],
                    'content' => 'tu es dispo samedi ?',
                    'is_seen' => true,
                ],
            ],
            'totalItems' => 1,
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

    /** @return iterable<string, array{string}> */
    public static function receiptsThatDoNotApply(): iterable
    {
        yield 'the other person hides that they read' => ['other_opted_out'];
        yield 'you hide that you read, so you see nobody' => ['me_opted_out'];
        yield 'you blocked them' => ['i_blocked'];
        yield 'they blocked you' => ['they_blocked'];
        yield 'their account is gone' => ['other_deleted'];
    }

    #[DataProvider('receiptsThatDoNotApply')]
    public function test_no_receipt_when_it_does_not_apply_between_the_two(string $case): void
    {
        $me = $this->user('alice_drums');
        $other = $case === 'other_deleted'
            ? UserFactory::new()->asBaseUser()->create([
                'username' => 'bob_bass',
                'email' => 'bob_bass@test.com',
                'deletionDatetime' => new \DateTimeImmutable('2026-10-21 10:00:00'),
            ])
            : $this->user('bob_bass');
        match ($case) {
            'other_opted_out' => $this->hideReceipts($other),
            'me_opted_out' => $this->hideReceipts($me),
            'i_blocked' => UserBlockFactory::new()->create(['blocker' => $me, 'blocked' => $other]),
            'they_blocked' => UserBlockFactory::new()->create(['blocker' => $other, 'blocked' => $me]),
            default => null,
        };
        $thread = $this->directThread($me, $other);
        $mine = $this->message($thread, $me, 'tu es dispo samedi ?', '2026-10-20 10:00:00');
        $this->positions($thread, $other, read: '2026-10-20 11:00:00', seen: '2026-10-20 11:00:00');

        $this->client->loginUser($me);
        $this->client->request('GET', '/api/messages/' . $thread->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $thread->id,
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/messages/' . $mine->id,
                    '@type' => 'Message',
                    'creation_datetime' => $mine->creationDatetime->format(\DateTimeInterface::ATOM),
                    'author' => [
                        '@id' => '/api/users/' . $me->id,
                        '@type' => 'User',
                        'id' => $me->id,
                        'username' => $me->username,
                        'display_name' => $me->username,
                    ],
                    'content' => 'tu es dispo samedi ?',
                    'is_seen' => false,
                ],
            ],
            'totalItems' => 1,
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

    private function message(MessageThread $thread, User $author, string $content, string $at): Message
    {
        return MessageFactory::new([
            'author' => $author,
            'thread' => $thread,
            'content' => $content,
            'creationDatetime' => new \DateTime($at),
        ])->create();
    }

    private function positions(MessageThread $thread, User $user, ?string $read, ?string $seen): void
    {
        MessageThreadMetaFactory::new([
            'thread' => $thread,
            'user' => $user,
            'lastReadDatetime' => $read === null ? null : new \DateTimeImmutable($read),
            'lastSeenDatetime' => $seen === null ? null : new \DateTimeImmutable($seen),
        ])->create();
    }

    private function hideReceipts(User $user): void
    {
        $preference = new UserNotificationPreference();
        $preference->user = $user;
        $preference->showReadReceipts = false;
        $user->notificationPreference = $preference;
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($preference);
        $entityManager->flush();
    }
}
