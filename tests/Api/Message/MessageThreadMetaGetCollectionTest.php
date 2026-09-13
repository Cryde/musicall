<?php

namespace App\Tests\Api\Message;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Enum\BandSpace\MembershipStatus;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageMentionFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class MessageThreadMetaGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $this->client->request('GET', '/api/message_thread_metas', );
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_get_message_thread_meta(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);
        $user3 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_3', 'email' => 'base_user3@email.com']);

        $thread = MessageThreadFactory::new()->create();
        $mp1 = MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user1])->create();
        $mp2 = MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user2])->create();
        $message = MessageFactory::new([
            'author' => $user1,
            'thread' => $thread,
            'content' => 'basic_content with <b>html</b> in it'
        ])->create();
        $thread->lastMessage = $message;
        \Zenstruck\Foundry\Persistence\save($thread);
        // An earlier message from the other participant, so the count under test is non-zero. Without
        // it the only message is $user1's own, and a zero would pass against an implementation that
        // always returned zero.
        MessageFactory::new([
            'author' => $user2,
            'thread' => $thread,
            'content' => 'something to read',
            'creationDatetime' => new \DateTime('2026-08-01 09:00:00'),
        ])->create();
        $meta = MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread])->create();

        // thread between user2 & user3 : shouldn't appear in the response
        $otherThread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $otherThread, 'participant' => $user2])->create();
        MessageParticipantFactory::new(['thread' => $otherThread, 'participant' => $user3])->create();
        $message2 = MessageFactory::new(['author' => $user2, 'thread' => $otherThread, 'content' => ''])->create();
        $otherThread->lastMessage = $message2;
        \Zenstruck\Foundry\Persistence\save($otherThread);
        MessageThreadMetaFactory::new(['user' => $user2, 'thread' => $otherThread])->create();

        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/message_thread_metas', );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'         => '/api/contexts/MessageThreadMeta',
            '@id'              => '/api/message_thread_metas',
            '@type'            => 'Collection',
            'member'     => [
                [
                    '@id' => '/api/message_thread_metas/' . $meta->id,
                    '@type' => 'MessageThreadMeta',
                    'id'      => $meta->id,
                    'unread_count' => 1,
                    'thread'  => [
                        '@id' => '/api/message_threads/' . $thread->id,
                        '@type' => 'MessageThread',
                        'id'                   => $thread->id,
                        'message_participants' => [
                            [
                                '@id' => '/api/message_participants/' . $mp1->id,
                                '@type' => 'MessageParticipant',
                                'participant' => [
                                    '@id' => '/api/users/' . $user1->id,
                                    '@type' => 'User',
                                    'username' => 'base_user_1',
                                    'id'       => $user1->id,
                                ],
                            ], [
                                '@id' => '/api/message_participants/' . $mp2->id,
                                '@type' => 'MessageParticipant',
                                'participant' => [
                                    '@id' => '/api/users/' . $user2->id,
                                    '@type' => 'User',
                                    'username' => 'base_user_2',
                                    'id'       => $user2->id,
                                ],
                            ],
                        ],
                        'last_message'         => [
                            '@id' => '/api/messages/' . $message->id,
                            '@type' => 'Message',
                            'creation_datetime' => $message->creationDatetime->format('c'),
                            'author'            => [
                                '@id' => '/api/users/' . $user1->id,
                                '@type' => 'User',
                                'username' => 'base_user_1',
                                'id'       => $user1->id,
                            ],
                            'content'           => 'basic_content with  in it',
                            'content_preview'   => 'basic_content with in it',
                        ],
                    ],
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_a_band_space_channel_appears_for_a_member(): void
    {
        // The inverse of what this asserted until #994. A channel now belongs in the inbox beside the
        // direct messages, carrying what it takes to label a row that has no other participant to be
        // named after, and to reach the chat API, which is addressed by space rather than by thread.
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $bandSpace = BandSpaceFactory::new(['name' => 'Les Trois Accords'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // No participant row, because a real channel has none: its members are derived from the space.
        $channel = MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
        $message = MessageFactory::new([
            'author' => $user,
            'thread' => $channel,
            'content' => 'dans le groupe',
            'creationDatetime' => new \DateTime('2026-09-01 10:00:00'),
        ])->create();
        $channel->lastMessage = $message;
        \Zenstruck\Foundry\Persistence\save($channel);
        $meta = MessageThreadMetaFactory::new(['user' => $user, 'thread' => $channel])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/message_thread_metas');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/MessageThreadMeta',
            '@id'        => '/api/message_thread_metas',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'   => '/api/message_thread_metas/' . $meta->id,
                    '@type' => 'MessageThreadMeta',
                    'id'    => (string) $meta->id,
                    // Own message, and you have read what you wrote.
                    'unread_count' => 0,
                    'thread' => [
                        '@id'   => '/api/message_threads/' . $channel->id,
                        '@type' => 'MessageThread',
                        'id'    => (string) $channel->id,
                        'message_participants' => [],
                        'last_message' => [
                            '@id'   => '/api/messages/' . $message->id,
                            '@type' => 'Message',
                            'creation_datetime' => '2026-09-01T10:00:00+00:00',
                            'author' => [
                                '@id'   => '/api/users/' . $user->id,
                                '@type' => 'User',
                                'id'    => (string) $user->id,
                                'username' => 'base_user_1',
                            ],
                            'content' => 'dans le groupe',
                            'content_preview' => 'dans le groupe',
                        ],
                        'band_space_id'   => (string) $bandSpace->id,
                        'band_space_name' => 'Les Trois Accords',
                        'channel_name'    => 'Général',
                    ],
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_a_channel_preview_names_who_was_mentioned(): void
    {
        // A mention is stored as `@[uuid]` and rendered only when the chat API reads it, so the inbox
        // preview, which is built from the stored content, would otherwise show the raw token (#994).
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $writer = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);
        $bandSpace = BandSpaceFactory::new(['name' => 'Les Trois Accords'])->create();
        foreach ([$user, $writer] as $member) {
            BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        }

        $channel = MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
        $message = MessageFactory::new([
            'author' => $writer,
            'thread' => $channel,
            'content' => 'salut @[' . $user->id . '], on répète @[tous] ?',
            'creationDatetime' => new \DateTime('2026-09-01 10:00:00'),
        ])->create();
        MessageMentionFactory::new(['message' => $message, 'mentionedUser' => $user])->create();
        $channel->lastMessage = $message;
        \Zenstruck\Foundry\Persistence\save($channel);
        $meta = MessageThreadMetaFactory::new(['user' => $user, 'thread' => $channel])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/message_thread_metas');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/MessageThreadMeta',
            '@id'        => '/api/message_thread_metas',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'   => '/api/message_thread_metas/' . $meta->id,
                    '@type' => 'MessageThreadMeta',
                    'id'    => (string) $meta->id,
                    'unread_count' => 1,
                    'thread' => [
                        '@id'   => '/api/message_threads/' . $channel->id,
                        '@type' => 'MessageThread',
                        'id'    => (string) $channel->id,
                        'message_participants' => [],
                        'last_message' => [
                            '@id'   => '/api/messages/' . $message->id,
                            '@type' => 'Message',
                            'creation_datetime' => '2026-09-01T10:00:00+00:00',
                            'author' => [
                                '@id'   => '/api/users/' . $writer->id,
                                '@type' => 'User',
                                'id'    => (string) $writer->id,
                                'username' => 'base_user_2',
                            ],
                            // The same span the chat renders, so a row opened from here and the same
                            // message read in the band space tab cannot style a mention differently.
                            'content' => 'salut <span class="chat-mention">@base_user_1</span>, on répète <span class="chat-mention">@tous</span> ?',
                            // Plain text, because this one is printed and never passed to v-html.
                            'content_preview' => 'salut @base_user_1, on répète @tous ?',
                        ],
                        'band_space_id'   => (string) $bandSpace->id,
                        'band_space_name' => 'Les Trois Accords',
                        'channel_name'    => 'Général',
                    ],
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_a_direct_message_is_left_exactly_as_it_was_typed(): void
    {
        // The mention renderer runs for a channel only. Somebody typing those characters into a direct
        // message means those characters, and answering them with a name, or with « inconnu », would
        // be inventing content they did not write.
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);
        $thread = MessageThreadFactory::new()->create();
        $mine = MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user])->create();
        $theirs = MessageParticipantFactory::new(['thread' => $thread, 'participant' => $other])->create();

        $message = MessageFactory::new([
            'author' => $other,
            'thread' => $thread,
            'content' => 'regarde @[' . $user->id . ']',
            'creationDatetime' => new \DateTime('2026-09-01 10:00:00'),
        ])->create();
        $thread->lastMessage = $message;
        \Zenstruck\Foundry\Persistence\save($thread);
        $meta = MessageThreadMetaFactory::new(['user' => $user, 'thread' => $thread])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/message_thread_metas');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/MessageThreadMeta',
            '@id'        => '/api/message_thread_metas',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'   => '/api/message_thread_metas/' . $meta->id,
                    '@type' => 'MessageThreadMeta',
                    'id'    => (string) $meta->id,
                    'unread_count' => 1,
                    'thread' => [
                        '@id'   => '/api/message_threads/' . $thread->id,
                        '@type' => 'MessageThread',
                        'id'    => (string) $thread->id,
                        'message_participants' => [
                            [
                                '@id'   => '/api/message_participants/' . $mine->id,
                                '@type' => 'MessageParticipant',
                                'participant' => [
                                    '@id'   => '/api/users/' . $user->id,
                                    '@type' => 'User',
                                    'id'    => (string) $user->id,
                                    'username' => 'base_user_1',
                                ],
                            ],
                            [
                                '@id'   => '/api/message_participants/' . $theirs->id,
                                '@type' => 'MessageParticipant',
                                'participant' => [
                                    '@id'   => '/api/users/' . $other->id,
                                    '@type' => 'User',
                                    'id'    => (string) $other->id,
                                    'username' => 'base_user_2',
                                ],
                            ],
                        ],
                        'last_message' => [
                            '@id'   => '/api/messages/' . $message->id,
                            '@type' => 'Message',
                            'creation_datetime' => '2026-09-01T10:00:00+00:00',
                            'author' => [
                                '@id'   => '/api/users/' . $other->id,
                                '@type' => 'User',
                                'id'    => (string) $other->id,
                                'username' => 'base_user_2',
                            ],
                            'content' => 'regarde &#64;[' . $user->id . ']',
                            'content_preview' => 'regarde @[' . $user->id . ']',
                        ],
                    ],
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_a_band_space_channel_is_gone_once_you_leave_the_band(): void
    {
        // The read-state row survives a leave or a kick (#948 concerns 1 and 3), so without the
        // membership filter a former member keeps the channel in their inbox, unread count and last
        // message preview included. This is the part that leaks if it is skipped.
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $writer = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => MembershipStatus::Kicked,
        ])->create();

        $channel = MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
        $message = MessageFactory::new(['author' => $writer, 'thread' => $channel, 'content' => 'sans toi'])->create();
        $channel->lastMessage = $message;
        \Zenstruck\Foundry\Persistence\save($channel);
        MessageThreadMetaFactory::new(['user' => $user, 'thread' => $channel])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/message_thread_metas');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/MessageThreadMeta',
            '@id'        => '/api/message_thread_metas',
            '@type'      => 'Collection',
            'member'     => [],
            'totalItems' => 0,
        ]);
    }

    public function test_a_channel_nobody_has_written_in_stays_out(): void
    {
        // Deliberate rather than inherited: the listing sorts on the last message and shows its
        // preview, so a conversation with nothing in it has nothing to sort by and nothing to show.
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $channel = MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
        MessageThreadMetaFactory::new(['user' => $user, 'thread' => $channel])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/message_thread_metas');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/MessageThreadMeta',
            '@id'        => '/api/message_thread_metas',
            '@type'      => 'Collection',
            'member'     => [],
            'totalItems' => 0,
        ]);
    }

    public function test_the_preview_of_the_last_message_carries_no_markup(): void
    {
        // The reported bug: the inbox printed the field the thread renders with v-html, so a message
        // written across three lines read "Bonjour Amy,<br /> <br /> J&#039;ai vu ..." in the list.
        // The content below carries all four shapes at once: newlines, an apostrophe, a tag the sender
        // typed, and angle brackets in ordinary prose.
        $reader = UserFactory::new()->asBaseUser()->create(['username' => 'reader', 'email' => 'reader@email.com']);
        $writer = UserFactory::new()->asBaseUser()->create(['username' => 'writer', 'email' => 'writer@email.com']);

        $thread = MessageThreadFactory::new()->create();
        $mp1 = MessageParticipantFactory::new(['thread' => $thread, 'participant' => $reader])->create();
        $mp2 = MessageParticipantFactory::new(['thread' => $thread, 'participant' => $writer])->create();
        $message = MessageFactory::new([
            'author' => $writer,
            'thread' => $thread,
            'content' => "Bonjour Harry,\n\nJ'ai vu <b>ton</b> retour, on commence < 20h",
        ])->create();
        $thread->lastMessage = $message;
        \Zenstruck\Foundry\Persistence\save($thread);
        $meta = MessageThreadMetaFactory::new([
            'user' => $reader,
            'thread' => $thread,
            'lastReadDatetime' => null,
        ])->create();

        $this->client->loginUser($reader);
        $this->client->request('GET', '/api/message_thread_metas');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/message_thread_metas/' . $meta->id,
                    '@type' => 'MessageThreadMeta',
                    'id' => $meta->id,
                    'unread_count' => 1,
                    'thread' => [
                        '@id' => '/api/message_threads/' . $thread->id,
                        '@type' => 'MessageThread',
                        'id' => $thread->id,
                        'message_participants' => [
                            [
                                '@id' => '/api/message_participants/' . $mp1->id,
                                '@type' => 'MessageParticipant',
                                'participant' => [
                                    '@id' => '/api/users/' . $reader->id,
                                    '@type' => 'User',
                                    'id' => $reader->id,
                                    'username' => 'reader',
                                ],
                            ], [
                                '@id' => '/api/message_participants/' . $mp2->id,
                                '@type' => 'MessageParticipant',
                                'participant' => [
                                    '@id' => '/api/users/' . $writer->id,
                                    '@type' => 'User',
                                    'id' => $writer->id,
                                    'username' => 'writer',
                                ],
                            ],
                        ],
                        'last_message' => [
                            '@id' => '/api/messages/' . $message->id,
                            '@type' => 'Message',
                            'creation_datetime' => $message->creationDatetime->format('c'),
                            'author' => [
                                '@id' => '/api/users/' . $writer->id,
                                '@type' => 'User',
                                'id' => $writer->id,
                                'username' => 'writer',
                            ],
                            // What the thread renders with v-html.
                            'content' => "Bonjour Harry,<br />\n<br />\nJ&#039;ai vu  retour, on commence &lt; 20h",
                            // What the inbox prints. One line, the apostrophe is an apostrophe, and the
                            // tag the sender typed is gone rather than shown at them. The `<` of
                            // "< 20h" stays, because that is a character in their sentence and it is
                            // what the thread puts on screen too: this field is text, not inert HTML.
                            'content_preview' => "Bonjour Harry, J'ai vu retour, on commence < 20h",
                        ],
                    ],
                ],
            ],
            'totalItems' => 1,
        ]);
    }
}
