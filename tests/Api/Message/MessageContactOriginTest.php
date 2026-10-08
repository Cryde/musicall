<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageContactOrigin;
use App\Entity\Message\MessageThread;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Enum\Message\ContactOriginType;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Message\MessageContactOriginFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\Teacher\TeacherProfileFactory;
use App\Tests\Factory\Teacher\TeacherProfileInstrumentFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\Message\ValidContactOriginValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A direct message remembers the announce or teacher profile it was sent from (#998).
 */
#[ResetDatabase]
class MessageContactOriginTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array JSON = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_a_message_sent_from_an_announce_carries_it(): void
    {
        [$sender, $recipient] = $this->pair();
        $announce = $this->announce($recipient);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Toujours dispo pour le groupe ?',
            'musicianAnnounceId' => $announce->id,
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = $this->onlyMessage();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $message->id,
            '@type' => 'Message',
            'id' => $message->id,
            'creation_datetime' => $message->creationDatetime->format('c'),
            'author' => [
                '@id' => '/api/users/' . $sender->id,
                '@type' => 'User',
                'id' => $sender->id,
                'username' => 'sender',
                'display_name' => 'sender',
            ],
            'thread' => [
                '@id' => '/api/message_threads/' . $message->thread->id,
                '@type' => 'MessageThread',
                'id' => $message->thread->id,
            ],
            'content' => 'Toujours dispo pour le groupe ?',
            'content_preview' => 'Toujours dispo pour le groupe ?',
            'contact_origin' => [
                'type' => 'musician_announce',
                'musician_announce_id' => $announce->id,
                'teacher_profile_id' => null,
                'announce_type' => MusicianAnnounce::TYPE_MUSICIAN,
                'instruments' => ['Batteur'],
                'styles' => ['Rock', 'Blues'],
                'location_name' => 'Lyon',
            ],
        ]);
    }

    public function test_a_message_sent_from_a_teacher_profile_carries_it(): void
    {
        [$sender, $recipient] = $this->pair();
        $profile = TeacherProfileFactory::new(['user' => $recipient])->create();
        TeacherProfileInstrumentFactory::new(['teacherProfile' => $profile, 'instrument' => InstrumentFactory::new(['name' => 'Piano'])])->create();
        self::getContainer()->get(EntityManagerInterface::class)->refresh($profile);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour, vous donnez des cours le samedi ?',
            'fromTeacherProfile' => true,
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = $this->onlyMessage();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $message->id,
            '@type' => 'Message',
            'id' => $message->id,
            'creation_datetime' => $message->creationDatetime->format('c'),
            'author' => [
                '@id' => '/api/users/' . $sender->id,
                '@type' => 'User',
                'id' => $sender->id,
                'username' => 'sender',
                'display_name' => 'sender',
            ],
            'thread' => [
                '@id' => '/api/message_threads/' . $message->thread->id,
                '@type' => 'MessageThread',
                'id' => $message->thread->id,
            ],
            'content' => 'Bonjour, vous donnez des cours le samedi ?',
            'content_preview' => 'Bonjour, vous donnez des cours le samedi ?',
            'contact_origin' => [
                'type' => 'teacher_profile',
                'musician_announce_id' => null,
                'teacher_profile_id' => (string) $profile->id,
                'announce_type' => null,
                'instruments' => ['Piano'],
                'styles' => [],
                'location_name' => null,
            ],
        ]);
    }

    public function test_an_announce_of_somebody_else_is_refused(): void
    {
        [$sender, $recipient] = $this->pair();
        $stranger = UserFactory::new()->asBaseUser()->create(['username' => 'stranger', 'email' => 'stranger@example.com']);
        $announce = $this->announce($stranger);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'musicianAnnounceId' => $announce->id,
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation('musicianAnnounceId', 'Cette annonce n\'existe plus ou n\'appartient pas au destinataire', ValidContactOriginValidator::ERROR_CODE_ANNOUNCE));
        $this->assertSame(0, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Message::class)->count([]));
    }

    public function test_an_id_that_is_not_a_uuid_is_refused(): void
    {
        [$sender, $recipient] = $this->pair();

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'musicianAnnounceId' => 'not-a-uuid',
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation('musicianAnnounceId', 'Cette annonce n\'existe plus ou n\'appartient pas au destinataire', ValidContactOriginValidator::ERROR_CODE_ANNOUNCE));
    }

    public function test_a_recipient_without_a_teacher_profile_is_refused(): void
    {
        [$sender, $recipient] = $this->pair();

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'fromTeacherProfile' => true,
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation('fromTeacherProfile', 'Le destinataire n\'a pas de profil de professeur', ValidContactOriginValidator::ERROR_CODE_TEACHER_PROFILE));
    }

    public function test_an_announce_and_a_teacher_profile_together_are_refused(): void
    {
        [$sender, $recipient] = $this->pair();
        $announce = $this->announce($recipient);
        $profile = TeacherProfileFactory::new(['user' => $recipient])->create();

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'musicianAnnounceId' => $announce->id,
            'fromTeacherProfile' => true,
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation('fromTeacherProfile', 'Un message part d\'une annonce ou d\'un profil de professeur, pas des deux', ValidContactOriginValidator::ERROR_CODE_BOTH));
    }

    /** Writing again from the announce the conversation is already about adds no second card. */
    public function test_writing_again_from_the_same_announce_records_nothing_new(): void
    {
        [$sender, $recipient] = $this->pair();
        $announce = $this->announce($recipient);
        $thread = $this->thread($sender, $recipient);
        $this->origin($thread, $sender, $announce, '2026-09-01 10:00:00');

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Je relance',
            'musicianAnnounceId' => $announce->id,
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $sent = $this->latestMessage();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $sent->id,
            '@type' => 'Message',
            'id' => $sent->id,
            'creation_datetime' => $sent->creationDatetime->format('c'),
            'author' => [
                '@id' => '/api/users/' . $sender->id,
                '@type' => 'User',
                'id' => $sender->id,
                'username' => 'sender',
                'display_name' => 'sender',
            ],
            'thread' => [
                '@id' => '/api/message_threads/' . $thread->id,
                '@type' => 'MessageThread',
                'id' => $thread->id,
            ],
            'content' => 'Je relance',
            'content_preview' => 'Je relance',
        ]);
        $this->assertSame(1, self::getContainer()->get(EntityManagerInterface::class)->getRepository(MessageContactOrigin::class)->count([]));
    }

    /** A contact from another announce is a new reason to talk, so it gets its own card. */
    public function test_writing_from_another_announce_records_it(): void
    {
        [$sender, $recipient] = $this->pair();
        $first = $this->announce($recipient);
        $second = $this->announce($recipient, 'Bassiste');
        $thread = $this->thread($sender, $recipient);
        $this->origin($thread, $sender, $first, '2026-09-01 10:00:00');

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Et pour la basse ?',
            'musicianAnnounceId' => $second->id,
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $sent = $this->latestMessage();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $sent->id,
            '@type' => 'Message',
            'id' => $sent->id,
            'creation_datetime' => $sent->creationDatetime->format('c'),
            'author' => [
                '@id' => '/api/users/' . $sender->id,
                '@type' => 'User',
                'id' => $sender->id,
                'username' => 'sender',
                'display_name' => 'sender',
            ],
            'thread' => [
                '@id' => '/api/message_threads/' . $thread->id,
                '@type' => 'MessageThread',
                'id' => $thread->id,
            ],
            'content' => 'Et pour la basse ?',
            'content_preview' => 'Et pour la basse ?',
            'contact_origin' => [
                'type' => 'musician_announce',
                'musician_announce_id' => $second->id,
                'teacher_profile_id' => null,
                'announce_type' => MusicianAnnounce::TYPE_MUSICIAN,
                'instruments' => ['Bassiste'],
                'styles' => ['Rock', 'Blues'],
                'location_name' => 'Lyon',
            ],
        ]);
        $this->assertSame(2, self::getContainer()->get(EntityManagerInterface::class)->getRepository(MessageContactOrigin::class)->count([]));
    }

    /** The inbox row recalls the most recent reason, not the first one. */
    public function test_the_inbox_shows_the_latest_origin(): void
    {
        [$sender, $recipient] = $this->pair();
        $thread = $this->thread($sender, $recipient);
        $this->origin($thread, $sender, $this->announce($recipient), '2026-09-01 10:00:00');
        $latest = $this->origin($thread, $sender, $this->announce($recipient, 'Bassiste'), '2026-09-20 10:00:00');
        $meta = MessageThreadMetaFactory::new(['thread' => $thread, 'user' => $recipient])->create();
        $participants = $thread->messageParticipants->toArray();
        $lastMessage = $latest->message;

        $this->client->loginUser($recipient);
        $this->client->request('GET', '/api/message_thread_metas', [], [], self::JSON);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/message_thread_metas/' . $meta->id,
                    '@type' => 'MessageThreadMeta',
                    'id' => (string) $meta->id,
                    'unread_count' => 2,
                    'thread' => [
                        '@id' => '/api/message_threads/' . $thread->id,
                        '@type' => 'MessageThread',
                        'id' => (string) $thread->id,
                        'message_participants' => [
                            [
                                '@id' => '/api/message_participants/' . $participants[0]->id,
                                '@type' => 'MessageParticipant',
                                'participant' => [
                                    '@id' => '/api/users/' . $sender->id,
                                    '@type' => 'User',
                                    'id' => $sender->id,
                                    'username' => 'sender',
                                    'display_name' => 'sender',
                                ],
                            ],
                            [
                                '@id' => '/api/message_participants/' . $participants[1]->id,
                                '@type' => 'MessageParticipant',
                                'participant' => [
                                    '@id' => '/api/users/' . $recipient->id,
                                    '@type' => 'User',
                                    'id' => $recipient->id,
                                    'username' => 'recipient',
                                    'display_name' => 'recipient',
                                ],
                            ],
                        ],
                        'last_message' => [
                            '@id' => '/api/messages/' . $lastMessage->id,
                            '@type' => 'Message',
                            'creation_datetime' => $lastMessage->creationDatetime->format('c'),
                            'author' => [
                                '@id' => '/api/users/' . $sender->id,
                                '@type' => 'User',
                                'id' => $sender->id,
                                'username' => 'sender',
                                'display_name' => 'sender',
                            ],
                            'content' => 'Bonjour',
                            'content_preview' => 'Bonjour',
                        ],
                        'latest_contact_origin' => [
                            'type' => 'musician_announce',
                            'musician_announce_id' => (string) $latest->musicianAnnounce?->id,
                            'teacher_profile_id' => null,
                            'announce_type' => MusicianAnnounce::TYPE_MUSICIAN,
                            'instruments' => ['Bassiste'],
                            'styles' => ['Rock', 'Blues'],
                            'location_name' => 'Lyon',
                        ],
                    ],
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    /** The announce is gone once someone is found; the conversation still says what it was about. */
    public function test_a_deleted_announce_leaves_its_snapshot(): void
    {
        [$sender, $recipient] = $this->pair();
        $announce = $this->announce($recipient);
        $thread = $this->thread($sender, $recipient);
        $origin = $this->origin($thread, $sender, $announce, '2026-09-01 10:00:00');
        $message = $origin->message;
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->remove($announce);
        $entityManager->flush();
        $entityManager->clear();

        $this->client->loginUser($recipient);
        $this->client->request('GET', '/api/messages/' . $thread->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $thread->id,
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/messages/' . $message->id,
                    '@type' => 'Message',
                    'creation_datetime' => $message->creationDatetime->format(\DateTimeInterface::ATOM),
                    'author' => [
                        '@id' => '/api/users/' . $sender->id,
                        '@type' => 'User',
                        'id' => $sender->id,
                        'username' => 'sender',
                        'display_name' => 'sender',
                    ],
                    'content' => 'Bonjour',
                    'contact_origin' => [
                        'type' => 'musician_announce',
                        'musician_announce_id' => null,
                        'teacher_profile_id' => null,
                        'announce_type' => MusicianAnnounce::TYPE_MUSICIAN,
                        'instruments' => ['Batteur'],
                        'styles' => ['Rock', 'Blues'],
                        'location_name' => 'Lyon',
                    ],
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

    /**
     * @return array{0: User, 1: User}
     */
    private function pair(): array
    {
        return [
            UserFactory::new()->asBaseUser()->create(['username' => 'sender', 'email' => 'sender@example.com']),
            UserFactory::new()->asBaseUser()->create(['username' => 'recipient', 'email' => 'recipient@example.com']),
        ];
    }

    private function announce(User $author, string $instrument = 'Batteur'): MusicianAnnounce
    {
        return MusicianAnnounceFactory::new([
            'author' => $author,
            'type' => MusicianAnnounce::TYPE_MUSICIAN,
            'instrument' => InstrumentFactory::new(['musicianName' => $instrument]),
            'locationName' => 'Lyon',
        ])->withStyles([StyleFactory::findOrCreate(['name' => 'Rock']), StyleFactory::findOrCreate(['name' => 'Blues'])])->create();
    }

    private function thread(User $sender, User $recipient): MessageThread
    {
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $sender])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $recipient])->create();
        MessageThreadMetaFactory::new(['thread' => $thread, 'user' => $sender])->create();

        return $thread;
    }

    private function origin(MessageThread $thread, User $author, MusicianAnnounce $announce, string $sentAt): MessageContactOrigin
    {
        $message = MessageFactory::new(['thread' => $thread, 'author' => $author, 'content' => 'Bonjour', 'creationDatetime' => new \DateTime($sentAt)])->create();
        $thread->lastMessage = $message;
        \Zenstruck\Foundry\Persistence\save($thread);

        return MessageContactOriginFactory::new([
            'message' => $message,
            'type' => ContactOriginType::MusicianAnnounce,
            'musicianAnnounce' => $announce,
            'instruments' => [$announce->instrument->musicianName],
            'styles' => ['Rock', 'Blues'],
        ])->create();
    }

    private function latestMessage(): Message
    {
        return self::getContainer()->get(EntityManagerInterface::class)->getRepository(Message::class)
            ->findOneBy([], ['creationDatetime' => 'DESC']);
    }

    private function onlyMessage(): Message
    {
        $messages = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Message::class)->findAll();
        $this->assertCount(1, $messages);

        return $messages[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function violation(string $path, string $message, string $code): array
    {
        $snakePath = strtolower((string) preg_replace('/[A-Z]/', '_$0', $path));

        return [
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . $code,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => $snakePath, 'message' => $message, 'code' => $code],
            ],
            'detail' => $snakePath . ': ' . $message,
            'type' => '/validation_errors/' . $code,
            'title' => 'An error occurred',
            'description' => $snakePath . ': ' . $message,
        ];
    }
}
