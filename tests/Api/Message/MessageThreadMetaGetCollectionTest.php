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
                    'is_read' => false,
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
                        ],
                    ],
                ],
            ],
            'totalItems' => 1,
        ]);
    }
}
