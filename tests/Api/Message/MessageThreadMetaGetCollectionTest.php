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
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MessageThreadMetaGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
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
        $thread->_real()->setLastMessage($message->_real());
        $thread->_save();
        $meta = MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread])->create();

        // thread between user2 & user3 : shouldn't appear in the response
        $otherThread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $otherThread, 'participant' => $user2])->create();
        MessageParticipantFactory::new(['thread' => $otherThread, 'participant' => $user3])->create();
        $message2 = MessageFactory::new(['author' => $user2, 'thread' => $otherThread, 'content' => ''])->create();
        $otherThread->_real()->setLastMessage($message2->_real());
        $otherThread->_save();
        MessageThreadMetaFactory::new(['user' => $user2, 'thread' => $otherThread])->create();

        $this->client->loginUser($user1->_real());
        $this->client->request('GET', '/api/message_thread_metas', );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'         => '/api/contexts/MessageThreadMeta',
            '@id'              => '/api/message_thread_metas',
            '@type'            => 'Collection',
            'member'     => [
                [
                    '@id' => '/api/message_thread_metas/' . $meta->_real()->getId(),
                    '@type' => 'MessageThreadMeta',
                    'id'      => $meta->_real()->getId(),
                    'is_read' => false,
                    'thread'  => [
                        '@id' => '/api/message_threads/' . $thread->_real()->getId(),
                        '@type' => 'MessageThread',
                        'id'                   => $thread->_real()->getId(),
                        'message_participants' => [
                            [
                                '@id' => '/api/message_participants/' . $mp1->_real()->getId(),
                                '@type' => 'MessageParticipant',
                                'participant' => [
                                    '@id' => '/api/users/self',
                                    '@type' => 'User',
                                    'username' => 'base_user_1',
                                    'id'       => $user1->_real()->getId(),
                                ],
                            ], [
                                '@id' => '/api/message_participants/' . $mp2->_real()->getId(),
                                '@type' => 'MessageParticipant',
                                'participant' => [
                                    '@id' => '/api/users/self',
                                    '@type' => 'User',
                                    'username' => 'base_user_2',
                                    'id'       => $user2->_real()->getId(),
                                ],
                            ],
                        ],
                        'last_message'         => [
                            '@id' => '/api/messages/' . $message->_real()->getId(),
                            '@type' => 'Message',
                            'creation_datetime' => $message->_real()->getCreationDatetime()->format('c'),
                            'author'            => [
                                '@id' => '/api/users/self',
                                '@type' => 'User',
                                'username' => 'base_user_1',
                                'id'       => $user1->_real()->getId(),
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