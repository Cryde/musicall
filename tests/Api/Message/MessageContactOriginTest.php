<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Attribute\Style;
use App\Entity\Message\Message;
use App\Entity\Message\MessageContactOrigin;
use App\Entity\Message\MessageThread;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Entity\Teacher\TeacherProfile;
use App\Enum\Message\ContactOriginType;
use App\Enum\Message\LessonRequest;
use App\Repository\Message\MessageContactOriginRepository;
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
use PHPUnit\Framework\Attributes\DataProvider;
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
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => 'Toujours dispo pour le groupe ?',
            'contact_origin' => [
                'type' => 'musician_announce',
                'musician_announce_id' => $announce->id,
                'teacher_profile_id' => null,
                'announce_type' => MusicianAnnounce::TYPE_MUSICIAN,
                'instruments' => ['Batteur'],
                'styles' => ['Rock', 'Blues'],
                'location_name' => 'Lyon',
                'lesson_request' => null,
            ],
        ]);
        // Kept by slug (#1212), named only when read.
        $origin = self::getContainer()->get(MessageContactOriginRepository::class)->findByMessageIds([(string) $message->id])[(string) $message->id];
        $this->assertSame([$announce->instrument->slug], $origin->instruments);
        $this->assertEqualsCanonicalizing(array_map(static fn (Style $style): string => $style->slug, $announce->styles->toArray()), $origin->styles);
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
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => 'Bonjour, vous donnez des cours le samedi ?',
            'contact_origin' => [
                'type' => 'teacher_profile',
                'musician_announce_id' => null,
                'teacher_profile_id' => (string) $profile->id,
                'announce_type' => null,
                'instruments' => ['Piano'],
                'styles' => [],
                'location_name' => null,
                'lesson_request' => null,
            ],
        ]);
    }

    /** « Demander un cours d'essai » names the one instrument asked for, and says it is a request (#1322). */
    public function test_a_trial_request_names_the_instrument_chosen(): void
    {
        [$sender, $recipient] = $this->pair();
        $profile = $this->teacher($recipient, [InstrumentFactory::new()->asBass(), InstrumentFactory::new(['name' => 'Didjeridoo', 'slug' => 'didjeridoo'])]);
        $bass = $profile->instruments->first()->instrument;

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour, un essai de basse ?',
            'fromTeacherProfile' => true,
            'teacherInstrumentId' => $bass->id,
            'lessonRequest' => 'trial',
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
            'content' => 'Bonjour, un essai de basse ?',
            'content_preview' => 'Bonjour, un essai de basse ?',
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => 'Bonjour, un essai de basse ?',
            'contact_origin' => [
                'type' => 'teacher_profile',
                'musician_announce_id' => null,
                'teacher_profile_id' => (string) $profile->id,
                'announce_type' => null,
                'instruments' => ['Basse'],
                'styles' => [],
                'location_name' => null,
                'lesson_request' => 'trial',
            ],
        ]);
    }

    public function test_a_first_lesson_request_to_a_teacher_without_instrument(): void
    {
        [$sender, $recipient] = $this->pair();
        $profile = $this->teacher($recipient, []);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour, un premier cours ?',
            'fromTeacherProfile' => true,
            'lessonRequest' => 'first',
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
            'content' => 'Bonjour, un premier cours ?',
            'content_preview' => 'Bonjour, un premier cours ?',
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => 'Bonjour, un premier cours ?',
            'contact_origin' => [
                'type' => 'teacher_profile',
                'musician_announce_id' => null,
                'teacher_profile_id' => (string) $profile->id,
                'announce_type' => null,
                'instruments' => [],
                'styles' => [],
                'location_name' => null,
                'lesson_request' => 'first',
            ],
        ]);
    }

    /** A question first, then a request: the request is a new reason to talk, so it gets its own card. */
    public function test_a_lesson_request_after_a_question_records_it(): void
    {
        [$sender, $recipient] = $this->pair();
        $profile = $this->teacher($recipient, [InstrumentFactory::new()->asBass(), InstrumentFactory::new()->asPiano()]);
        $thread = $this->thread($sender, $recipient);
        $this->teacherOrigin($thread, $sender, $profile, null, ['basse', 'piano']);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Alors, un essai ?',
            'fromTeacherProfile' => true,
            'teacherInstrumentId' => $profile->instruments->first()->instrument->id,
            'lessonRequest' => 'trial',
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
            'content' => 'Alors, un essai ?',
            'content_preview' => 'Alors, un essai ?',
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => 'Alors, un essai ?',
            'contact_origin' => [
                'type' => 'teacher_profile',
                'musician_announce_id' => null,
                'teacher_profile_id' => (string) $profile->id,
                'announce_type' => null,
                'instruments' => ['Basse'],
                'styles' => [],
                'location_name' => null,
                'lesson_request' => 'trial',
            ],
        ]);
        $this->assertSame(2, self::getContainer()->get(EntityManagerInterface::class)->getRepository(MessageContactOrigin::class)->count([]));
    }

    public function test_the_same_lesson_request_twice_records_one_card(): void
    {
        [$sender, $recipient] = $this->pair();
        $profile = $this->teacher($recipient, [InstrumentFactory::new()->asBass(), InstrumentFactory::new()->asPiano()]);
        $thread = $this->thread($sender, $recipient);
        $this->teacherOrigin($thread, $sender, $profile, LessonRequest::Trial, ['basse']);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Je relance',
            'fromTeacherProfile' => true,
            'teacherInstrumentId' => $profile->instruments->first()->instrument->id,
            'lessonRequest' => 'trial',
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
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => 'Je relance',
        ]);
        $this->assertSame(1, self::getContainer()->get(EntityManagerInterface::class)->getRepository(MessageContactOrigin::class)->count([]));
    }

    /** The teacher's inbox row says a lesson is asked for (#1322). */
    public function test_the_inbox_shows_a_lesson_request(): void
    {
        [$sender, $recipient] = $this->pair();
        $profile = $this->teacher($recipient, [InstrumentFactory::new()->asBass(), InstrumentFactory::new()->asPiano()]);
        $thread = $this->thread($sender, $recipient);
        $origin = $this->teacherOrigin($thread, $sender, $profile, LessonRequest::Trial, ['basse']);
        $meta = MessageThreadMetaFactory::new(['thread' => $thread, 'user' => $recipient])->create();
        $participants = $thread->messageParticipants->toArray();
        $lastMessage = $origin->message;

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
                    'unread_count' => 1,
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
                            'type' => 'teacher_profile',
                            'musician_announce_id' => null,
                            'teacher_profile_id' => (string) $profile->id,
                            'announce_type' => null,
                            'instruments' => ['Basse'],
                            'styles' => [],
                            'location_name' => null,
                            'lesson_request' => 'trial',
                        ],
                        'can_send_media' => true,
                    ],
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    /** A question after a request is still about the same lessons: no second card. */
    public function test_a_question_after_a_lesson_request_records_nothing_new(): void
    {
        [$sender, $recipient] = $this->pair();
        $profile = $this->teacher($recipient, [InstrumentFactory::new()->asBass(), InstrumentFactory::new()->asPiano()]);
        $thread = $this->thread($sender, $recipient);
        $this->teacherOrigin($thread, $sender, $profile, LessonRequest::Trial, ['basse']);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Et le tarif ?',
            'fromTeacherProfile' => true,
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
            'content' => 'Et le tarif ?',
            'content_preview' => 'Et le tarif ?',
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => 'Et le tarif ?',
        ]);
        $this->assertSame(1, self::getContainer()->get(EntityManagerInterface::class)->getRepository(MessageContactOrigin::class)->count([]));
    }

    /** @return iterable<string, array{array<string, string>, string, string, string}> */
    public static function emptyLessonFieldProvider(): iterable
    {
        yield 'an empty instrument' => [['teacherInstrumentId' => ''], 'teacherInstrumentId', 'Le professeur n\'enseigne pas cet instrument', ValidContactOriginValidator::ERROR_CODE_TEACHER_INSTRUMENT];
        yield 'an empty lesson request' => [['lessonRequest' => ''], 'lessonRequest', 'Cette demande de cours n\'est pas valide', ValidContactOriginValidator::ERROR_CODE_LESSON_REQUEST];
    }

    /** @param array<string, string> $fields */
    #[DataProvider('emptyLessonFieldProvider')]
    public function test_an_empty_lesson_field_is_refused(array $fields, string $path, string $message, string $code): void
    {
        [$sender, $recipient] = $this->pair();
        $this->teacher($recipient, [InstrumentFactory::new()->asPiano()]);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'fromTeacherProfile' => true,
            ...$fields,
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation($path, $message, $code));
    }

    public function test_a_lesson_request_that_is_not_a_string_is_refused(): void
    {
        [$sender, $recipient] = $this->pair();
        $this->teacher($recipient, [InstrumentFactory::new()->asPiano()]);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'fromTeacherProfile' => true,
            'lessonRequest' => 1,
        ], self::JSON);

        // Refused by the serializer before the constraint runs, never read as « 1 ».
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'lesson_request', 'message' => 'Cette valeur doit être de type null|string.', 'code' => 'ba785a8c-82cb-4283-967c-3cf342181b40'],
            ],
            'detail' => 'lesson_request: Cette valeur doit être de type null|string.',
            'description' => 'lesson_request: Cette valeur doit être de type null|string.',
            'type' => '/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            'title' => 'An error occurred',
        ]);
    }

    /** Without a teacher profile there is nothing to ask lessons of: that alone is said. */
    public function test_a_lesson_request_to_someone_without_a_teacher_profile_is_refused_once(): void
    {
        [$sender, $recipient] = $this->pair();

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'fromTeacherProfile' => true,
            'teacherInstrumentId' => '0195f3a0-0000-7000-8000-000000000000',
            'lessonRequest' => 'trial',
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation('fromTeacherProfile', 'Le destinataire n\'a pas de profil de professeur', ValidContactOriginValidator::ERROR_CODE_TEACHER_PROFILE));
    }

    public function test_a_lesson_request_from_an_announce_is_refused(): void
    {
        [$sender, $recipient] = $this->pair();
        $announce = $this->announce($recipient);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'musicianAnnounceId' => $announce->id,
            'lessonRequest' => 'trial',
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation('lessonRequest', 'Une demande de cours part d\'un profil de professeur', ValidContactOriginValidator::ERROR_CODE_LESSON_WITHOUT_TEACHER_PROFILE));
        $this->assertSame(0, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Message::class)->count([]));
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function lessonFieldsProvider(): iterable
    {
        yield 'an instrument' => [['teacherInstrumentId' => '0195f3a0-0000-7000-8000-000000000000'], 'teacherInstrumentId'];
        yield 'a lesson request' => [['lessonRequest' => 'trial'], 'lessonRequest'];
    }

    /** @param array<string, string> $fields */
    #[DataProvider('lessonFieldsProvider')]
    public function test_a_lesson_request_only_comes_from_a_teacher_profile(array $fields, string $path): void
    {
        [$sender, $recipient] = $this->pair();
        $this->teacher($recipient, [InstrumentFactory::new()->asBass()]);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            ...$fields,
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation($path, 'Une demande de cours part d\'un profil de professeur', ValidContactOriginValidator::ERROR_CODE_LESSON_WITHOUT_TEACHER_PROFILE));
        $this->assertSame(0, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Message::class)->count([]));
    }

    public function test_an_instrument_the_teacher_does_not_teach_is_refused(): void
    {
        [$sender, $recipient] = $this->pair();
        $this->teacher($recipient, [InstrumentFactory::new()->asPiano()]);
        $bass = InstrumentFactory::new()->asBass()->create();

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'fromTeacherProfile' => true,
            'teacherInstrumentId' => $bass->id,
            'lessonRequest' => 'trial',
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation('teacherInstrumentId', 'Le professeur n\'enseigne pas cet instrument', ValidContactOriginValidator::ERROR_CODE_TEACHER_INSTRUMENT));
        $this->assertSame(0, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Message::class)->count([]));
    }

    public function test_an_instrument_id_that_is_not_a_uuid_is_refused(): void
    {
        [$sender, $recipient] = $this->pair();
        $this->teacher($recipient, [InstrumentFactory::new()->asPiano()]);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'fromTeacherProfile' => true,
            'teacherInstrumentId' => 'not-a-uuid',
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation('teacherInstrumentId', 'Le professeur n\'enseigne pas cet instrument', ValidContactOriginValidator::ERROR_CODE_TEACHER_INSTRUMENT));
    }

    public function test_an_unknown_lesson_request_is_refused(): void
    {
        [$sender, $recipient] = $this->pair();
        $this->teacher($recipient, [InstrumentFactory::new()->asPiano()]);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'fromTeacherProfile' => true,
            'lessonRequest' => 'free',
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation('lessonRequest', 'Cette demande de cours n\'est pas valide', ValidContactOriginValidator::ERROR_CODE_LESSON_REQUEST));
        $this->assertSame(0, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Message::class)->count([]));
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
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => 'Je relance',
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
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => 'Et pour la basse ?',
            'contact_origin' => [
                'type' => 'musician_announce',
                'musician_announce_id' => $second->id,
                'teacher_profile_id' => null,
                'announce_type' => MusicianAnnounce::TYPE_MUSICIAN,
                'instruments' => ['Bassiste'],
                'styles' => ['Rock', 'Blues'],
                'location_name' => 'Lyon',
                'lesson_request' => null,
            ],
        ]);
        $this->assertSame(2, self::getContainer()->get(EntityManagerInterface::class)->getRepository(MessageContactOrigin::class)->count([]));
    }

    /**
     * The inbox row recalls the most recent reason, not the first one, in the request language.
     *
     * @param list<string> $instruments
     * @param list<string> $styles
     */
    #[DataProvider('inboxOriginProvider')]
    public function test_the_inbox_shows_the_latest_origin(string $locale, array $instruments, array $styles): void
    {
        [$sender, $recipient] = $this->pair();
        $thread = $this->thread($sender, $recipient);
        $this->origin($thread, $sender, $this->announce($recipient), '2026-09-01 10:00:00');
        $bassAnnounce = MusicianAnnounceFactory::new([
            'author' => $recipient,
            'type' => MusicianAnnounce::TYPE_MUSICIAN,
            'instrument' => InstrumentFactory::new()->asBass(),
            'locationName' => 'Lyon',
        ])->withStyles([StyleFactory::findOrCreate(['name' => 'Rock']), StyleFactory::new(['name' => 'Électro', 'slug' => 'electro'])->create()])->create();
        $latest = $this->origin($thread, $sender, $bassAnnounce, '2026-09-20 10:00:00');
        $meta = MessageThreadMetaFactory::new(['thread' => $thread, 'user' => $recipient])->create();
        $participants = $thread->messageParticipants->toArray();
        $lastMessage = $latest->message;

        $this->client->loginUser($recipient);
        $this->client->request('GET', '/api/message_thread_metas', [], [], self::JSON + ['HTTP_X_LOCALE' => $locale]);

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
                            'instruments' => $instruments,
                            'styles' => $styles,
                            'location_name' => 'Lyon',
                            'lesson_request' => null,
                        ],
                        'can_send_media' => true,
                    ],
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    /** @return iterable<string, array{string, list<string>, list<string>}> */
    public static function inboxOriginProvider(): iterable
    {
        yield 'French' => ['fr', ['Bassiste'], ['Rock', 'Électro']];
        yield 'English' => ['en', ['Bassist'], ['Rock', 'Electronic']];
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
                    'id' => (string) $message->id,
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
                        'lesson_request' => null,
                    ],
                    'is_seen' => false,
                    'is_deleted' => false,
                    'reactions' => [],
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
     * A conversation from before #1212 holds slugs once migrated. One written by the old code during
     * the deploy window holds French names, matched too. Both read in the request language, French
     * reading the database name as before; a name matching nothing reads as stored.
     *
     * @param list<string> $announceInstruments
     * @param list<string> $announceStyles
     * @param list<string> $teacherInstruments
     */
    #[DataProvider('storedOriginProvider')]
    public function test_a_stored_origin_reads_in_the_request_language(string $locale, array $announceInstruments, array $announceStyles, array $teacherInstruments): void
    {
        InstrumentFactory::new()->asDrum()->create();
        InstrumentFactory::new()->asBass()->create();
        StyleFactory::new(['name' => 'Chanson Française', 'slug' => 'chanson-francaise'])->create();
        StyleFactory::new(['name' => 'Électro', 'slug' => 'electro'])->create();
        [$sender, $recipient] = $this->pair();
        $thread = $this->thread($sender, $recipient);
        $fromAnnounce = MessageFactory::new(['thread' => $thread, 'author' => $sender, 'content' => 'Bonjour', 'creationDatetime' => new \DateTime('2026-09-01 10:00:00')])->create();
        $fromTeacher = MessageFactory::new(['thread' => $thread, 'author' => $sender, 'content' => 'Et les cours ?', 'creationDatetime' => new \DateTime('2026-09-02 10:00:00')])->create();
        MessageContactOriginFactory::new([
            'message' => $fromAnnounce,
            'instruments' => ['Bassiste'],
            'styles' => ['chanson-francaise', 'Électro', 'Rock Sudiste'],
        ])->create();
        MessageContactOriginFactory::new([
            'message' => $fromTeacher,
            'type' => ContactOriginType::TeacherProfile,
            'announceType' => null,
            'instruments' => ['batterie', 'Basse', 'Ukulélé'],
            'styles' => [],
            'locationName' => null,
        ])->create();

        $this->client->loginUser($recipient);
        $this->client->request('GET', '/api/messages/' . $thread->id . '?order[creation_datetime]=asc', server: self::JSON + ['HTTP_X_LOCALE' => $locale]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $thread->id,
            '@type' => 'Collection',
            'member' => [
                $this->storedOriginMessage($fromAnnounce, $sender, [
                    'type' => 'musician_announce',
                    'musician_announce_id' => null,
                    'teacher_profile_id' => null,
                    'announce_type' => MusicianAnnounce::TYPE_MUSICIAN,
                    'instruments' => $announceInstruments,
                    'styles' => $announceStyles,
                    'location_name' => 'Lyon',
                    'lesson_request' => null,
                ]),
                $this->storedOriginMessage($fromTeacher, $sender, [
                    'type' => 'teacher_profile',
                    'musician_announce_id' => null,
                    'teacher_profile_id' => null,
                    'announce_type' => null,
                    'instruments' => $teacherInstruments,
                    'styles' => [],
                    'location_name' => null,
                    'lesson_request' => null,
                ]),
            ],
            'totalItems' => 2,
            'view' => [
                '@id' => '/api/messages/' . $thread->id . '?order%5Bcreation_datetime%5D=asc',
                '@type' => 'PartialCollectionView',
            ],
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

    /** @return iterable<string, array{string, list<string>, list<string>, list<string>}> */
    public static function storedOriginProvider(): iterable
    {
        yield 'French' => ['fr', ['Bassiste'], ['Chanson Française', 'Électro', 'Rock Sudiste'], ['Batterie', 'Basse', 'Ukulélé']];
        yield 'English' => ['en', ['Bassist'], ['French Chanson', 'Electronic', 'Rock Sudiste'], ['Drums', 'Bass', 'Ukulélé']];
    }

    /**
     * @param array<string, mixed> $contactOrigin
     *
     * @return array<string, mixed>
     */
    private function storedOriginMessage(Message $message, User $author, array $contactOrigin): array
    {
        return [
            '@id' => '/api/messages/' . $message->id,
            '@type' => 'Message',
            'id' => (string) $message->id,
            'creation_datetime' => $message->creationDatetime->format(\DateTimeInterface::ATOM),
            'author' => [
                '@id' => '/api/users/' . $author->id,
                '@type' => 'User',
                'id' => $author->id,
                'username' => 'sender',
                'display_name' => 'sender',
            ],
            'content' => $message->content,
            'contact_origin' => $contactOrigin,
            'is_seen' => false,
            'is_deleted' => false,
            'reactions' => [],
        ];
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
            'instruments' => [$announce->instrument->slug],
            'styles' => array_values(array_map(static fn (Style $style): string => $style->slug, $announce->styles->toArray())),
        ])->create();
    }

    /**
     * @param list<InstrumentFactory> $instruments taught in this order
     */
    private function teacher(User $user, array $instruments): TeacherProfile
    {
        $profile = TeacherProfileFactory::new(['user' => $user])->create();
        foreach ($instruments as $instrument) {
            TeacherProfileInstrumentFactory::new(['teacherProfile' => $profile, 'instrument' => $instrument])->create();
        }
        self::getContainer()->get(EntityManagerInterface::class)->refresh($profile);

        return $profile;
    }

    /**
     * @param list<string> $instruments slugs, as the card snapshots them
     */
    private function teacherOrigin(MessageThread $thread, User $author, TeacherProfile $profile, ?LessonRequest $lessonRequest, array $instruments): MessageContactOrigin
    {
        $message = MessageFactory::new(['thread' => $thread, 'author' => $author, 'content' => 'Bonjour', 'creationDatetime' => new \DateTime('2026-09-01 10:00:00')])->create();
        $thread->lastMessage = $message;
        \Zenstruck\Foundry\Persistence\save($thread);

        return MessageContactOriginFactory::new([
            'message' => $message,
            'type' => ContactOriginType::TeacherProfile,
            'teacherProfile' => $profile,
            'announceType' => null,
            'instruments' => $instruments,
            'styles' => [],
            'locationName' => null,
            'lessonRequest' => $lessonRequest,
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
