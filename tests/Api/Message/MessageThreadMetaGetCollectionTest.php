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

    public function test_not_logged()
    {
        $this->client->request('GET', '/api/message_thread_metas', );
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_get_message_thread_meta()
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);
        $user3 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_3', 'email' => 'base_user3@email.com']);

        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user1])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $user2])->create();
        $message = MessageFactory::new([
            'author' => $user1,
            'thread' => $thread,
            'content' => 'basic_content with <b>html</b> in it'
        ])->create();
        $thread->object()->setLastMessage($message->object());
        $thread->save();
        $meta = MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread])->create();

        // thread between user2 & user3 : shouldn't appear in the response
        $otherThread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $otherThread, 'participant' => $user2])->create();
        MessageParticipantFactory::new(['thread' => $otherThread, 'participant' => $user3])->create();
        $message = MessageFactory::new(['author' => $user2, 'thread' => $otherThread, 'content' => ''])->create();
        $otherThread->object()->setLastMessage($message->object());
        $otherThread->save();
        MessageThreadMetaFactory::new(['user' => $user2, 'thread' => $otherThread])->create();

        $this->client->loginUser($user1->object());
        $this->client->request('GET', '/api/message_thread_metas', );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'         => '/api/contexts/MessageThreadMeta',
            '@id'              => '/api/message_thread_metas',
            '@type'            => 'hydra:Collection',
            'hydra:member'     => [
                [
                    'id' => $meta->object()->getId(),
                    'is_read' => false,
                    'thread'  => [
                        '@type'                => 'MessageThread',
                        'id'                   => $thread->object()->getId(),
                        'message_participants' => [
                            [
                                '@type'       => 'MessageParticipant',
                                'participant' => [
                                    '@type'           => 'User',
                                    'username'        => 'base_user_1',
                                ],
                            ], [
                                '@type'       => 'MessageParticipant',
                                'participant' => [
                                    '@type'           => 'User',
                                    'username'        => 'base_user_2',
                                ],
                            ],
                        ],
                        'last_message'         => [
                            'creation_datetime' => $message->object()->getCreationDatetime()->format('c'),
                            'author'            => [
                                '@type'           => 'User',
                                'username'        => 'base_user_1',
                            ],
                            'content'           => 'basic_content with  in it',
                        ],
                    ],
                ],
            ],
            'hydra:totalItems' => 1,
        ]);
    }
}