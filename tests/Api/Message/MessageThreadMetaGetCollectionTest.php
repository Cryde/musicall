<?php

namespace App\Tests\Api\Message;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageFactory;
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
