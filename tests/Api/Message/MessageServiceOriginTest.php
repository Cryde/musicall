<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageContactOrigin;
use App\Entity\Message\MessageThread;
use App\Entity\Service\Service;
use App\Entity\User;
use App\Enum\Message\ContactOriginType;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\ServiceStatus;
use App\Repository\Message\MessageContactOriginRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageContactOriginFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\Message\ValidContactOriginValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * « Poser une question » and « Demander un devis » on a service page (#1408): the message carries the service, and a
 * quote request its answers.
 */
#[ResetDatabase]
class MessageServiceOriginTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array JSON = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_a_question_from_a_service_page_carries_the_service(): void
    {
        [$sender, $recipient] = $this->pair();
        $service = $this->soundEngineering($recipient);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Tu mixes aussi du jazz ?',
            'serviceId' => $service->id,
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = $this->onlyMessage();
        $this->assertJsonEquals($this->sentMessage($message, $sender, 'Tu mixes aussi du jazz ?', $this->serviceOrigin($service, null)));
    }

    public function test_a_quote_request_carries_its_answers_in_asking_order(): void
    {
        [$sender, $recipient] = $this->pair();
        $service = $this->soundEngineering($recipient);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour, un devis pour notre EP ?',
            'serviceId' => $service->id,
            'quote' => [
                'budget' => '300_to_1000',
                'deadline' => '2026-12-15',
                'link' => 'https://soundcloud.com/groupe/demo',
                'tracks' => 5,
                'need' => 'mixing',
            ],
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = $this->onlyMessage();
        $this->assertJsonEquals($this->sentMessage($message, $sender, 'Bonjour, un devis pour notre EP ?', $this->serviceOrigin($service, [
            'need' => 'mixing',
            'tracks' => 5,
            'link' => 'https://soundcloud.com/groupe/demo',
            'deadline' => '2026-12-15',
            'budget' => '300_to_1000',
        ])));
    }

    /** Only « what » is required: an answer left empty is not kept. */
    public function test_an_answer_left_empty_is_not_kept(): void
    {
        [$sender, $recipient] = $this->pair();
        $service = $this->soundEngineering($recipient);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Un devis pour un master ?',
            'serviceId' => $service->id,
            'quote' => ['need' => 'mastering', 'tracks' => null, 'link' => '', 'deadline' => '', 'budget' => null],
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = $this->onlyMessage();
        $this->assertJsonEquals($this->sentMessage($message, $sender, 'Un devis pour un master ?', $this->serviceOrigin($service, ['need' => 'mastering'])));
        $origin = self::getContainer()->get(MessageContactOriginRepository::class)->findByMessageIds([(string) $message->id])[(string) $message->id];
        $this->assertSame(['need' => 'mastering'], $origin->quote);
    }

    /** @return iterable<string, array{array<mixed>, string, string, string}> */
    public static function invalidQuoteProvider(): iterable
    {
        yield 'no need' => [['tracks' => 3], 'quote[need]', 'Ce champ est manquant.', '2fa2158c-2a7f-484b-98aa-975522539ff8'];
        yield 'an empty need' => [['need' => ''], 'quote[need]', 'Cette valeur ne doit pas être vide.', 'c1051bb4-d103-4f74-8988-acbcafc7fdc3'];
        yield 'an unknown need' => [['need' => 'cooking'], 'quote[need]', 'Cette valeur doit être l\'un des choix proposés.', '8e179f1b-97aa-4560-a02f-2a8b42e49df7'];
        yield 'a need that is not a string' => [['need' => ['mixing']], 'quote[need]', 'Cette valeur doit être l\'un des choix proposés.', '8e179f1b-97aa-4560-a02f-2a8b42e49df7'];
        yield 'an unknown question' => [['need' => 'mixing', 'genre' => 'jazz'], 'quote[genre]', 'Ce champ n\'a pas été prévu.', '7703c766-b5d5-4cef-ace7-ae0dd82304e9'];
        yield 'tracks as a string' => [['need' => 'mixing', 'tracks' => '5'], 'quote[tracks]', 'Cette valeur doit être de type int.', 'ba785a8c-82cb-4283-967c-3cf342181b40'];
        yield 'too many tracks' => [['need' => 'mixing', 'tracks' => 101], 'quote[tracks]', 'Cette valeur doit être comprise entre 1 et 100.', '04b91c99-a946-4221-afc5-e65ebac401eb'];
        yield 'no track' => [['need' => 'mixing', 'tracks' => 0], 'quote[tracks]', 'Cette valeur doit être comprise entre 1 et 100.', '04b91c99-a946-4221-afc5-e65ebac401eb'];
        yield 'a link that is not a url' => [['need' => 'mixing', 'link' => 'ma démo'], 'quote[link]', 'Cette valeur n\'est pas une URL valide.', '57c2f299-1154-4870-89bb-ef3b1f5ad229'];
        yield 'a link that is not a string' => [['need' => 'mixing', 'link' => ['https://x.test']], 'quote[link]', 'Cette valeur doit être de type string.', 'ba785a8c-82cb-4283-967c-3cf342181b40'];
        yield 'a link too long' => [['need' => 'mixing', 'link' => 'https://x.test/' . str_repeat('a', 241)], 'quote[link]', 'Cette chaîne est trop longue. Elle doit contenir au maximum 255 caractères.', 'd94b19cc-114f-4f44-9cc4-4138e80a87b9'];
        yield 'a javascript link' => [['need' => 'mixing', 'link' => 'javascript:alert(1)'], 'quote[link]', 'Cette valeur n\'est pas une URL valide.', '57c2f299-1154-4870-89bb-ef3b1f5ad229'];
        yield 'a day that does not exist' => [['need' => 'mixing', 'deadline' => '2026-02-31'], 'quote[deadline]', 'Cette valeur n\'est pas une date valide.', '3c184ce5-b31d-4de7-8b76-326da7b2be93'];
        yield 'a deadline that is not a string' => [['need' => 'mixing', 'deadline' => ['2026-12-15']], 'quote[deadline]', 'Cette valeur doit être de type string.', 'ba785a8c-82cb-4283-967c-3cf342181b40'];
        yield 'a deadline with a null byte' => [['need' => 'mixing', 'deadline' => "2026-12-15\0"], 'quote[deadline]', 'Cette valeur n\'est pas une date valide.', '69819696-02ac-4a99-9ff0-14e127c4d1bc'];
        yield 'an unknown budget' => [['need' => 'mixing', 'budget' => 'free'], 'quote[budget]', 'Cette valeur doit être l\'un des choix proposés.', '8e179f1b-97aa-4560-a02f-2a8b42e49df7'];
    }

    /** @param array<mixed> $quote */
    #[DataProvider('invalidQuoteProvider')]
    public function test_an_invalid_answer_is_refused(array $quote, string $path, string $message, string $code): void
    {
        [$sender, $recipient] = $this->pair();
        $service = $this->soundEngineering($recipient);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'serviceId' => $service->id,
            'quote' => $quote,
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation($path, $message, $code));
        $this->assertNoMessage();
    }

    public function test_a_quote_without_a_service_is_refused(): void
    {
        [$sender, $recipient] = $this->pair();

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'quote' => ['need' => 'mixing'],
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation('quote', 'Une demande de devis part de la page d\'un service', ValidContactOriginValidator::ERROR_CODE_QUOTE_WITHOUT_SERVICE));
        $this->assertNoMessage();
    }

    /** @return iterable<string, array{callable(User): string}> */
    public static function unusableServiceProvider(): iterable
    {
        yield 'somebody else\'s service' => [static fn (User $recipient): string => (string) ServiceFactory::new()->soundEngineering()->create()->id];
        yield 'a draft' => [static fn (User $recipient): string => (string) ServiceFactory::new(['user' => $recipient, 'status' => ServiceStatus::Draft])->soundEngineering()->create()->id];
        yield 'teaching, which says fromTeacherProfile' => [static fn (User $recipient): string => (string) ServiceFactory::new(['user' => $recipient])->teaching()->create()->id];
        yield 'an unknown id' => [static fn (User $recipient): string => '0195f3a0-0000-7000-8000-000000000000'];
        yield 'an id that is not a uuid' => [static fn (User $recipient): string => 'not-a-uuid'];
    }

    /** @param callable(User): string $serviceId */
    #[DataProvider('unusableServiceProvider')]
    public function test_a_service_the_recipient_does_not_show_is_refused(callable $serviceId): void
    {
        [$sender, $recipient] = $this->pair();
        $id = $serviceId($recipient);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'serviceId' => $id,
            'quote' => ['need' => 'mixing'],
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation('serviceId', 'Ce service n\'existe pas ou n\'appartient pas au destinataire', ValidContactOriginValidator::ERROR_CODE_SERVICE));
        $this->assertNoMessage();
    }

    public function test_a_service_and_a_teacher_profile_together_are_refused(): void
    {
        [$sender, $recipient] = $this->pair();
        $service = $this->soundEngineering($recipient);
        ServiceFactory::new(['user' => $recipient])->teaching()->create();

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'serviceId' => $service->id,
            'fromTeacherProfile' => true,
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation('serviceId', 'Un message part d\'une annonce, d\'un profil de professeur ou d\'un service, pas de plusieurs à la fois', ValidContactOriginValidator::ERROR_CODE_BOTH));
        $this->assertNoMessage();
    }

    public function test_a_lesson_request_from_a_service_page_is_refused(): void
    {
        [$sender, $recipient] = $this->pair();
        $service = $this->soundEngineering($recipient);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Bonjour',
            'serviceId' => $service->id,
            'lessonRequest' => 'trial',
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->violation('lessonRequest', 'Une demande de cours part d\'un profil de professeur', ValidContactOriginValidator::ERROR_CODE_LESSON_WITHOUT_TEACHER_PROFILE));
        $this->assertNoMessage();
    }

    public function test_the_inbox_shows_a_quote_request(): void
    {
        [$sender, $recipient] = $this->pair();
        $service = $this->soundEngineering($recipient);
        $thread = $this->thread($sender, $recipient);
        $origin = $this->quoteOrigin($thread, $sender, $service, ['need' => 'recording', 'budget' => 'under_100']);
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
                        'latest_contact_origin' => $this->serviceOrigin($service, ['need' => 'recording', 'budget' => 'under_100']),
                        'can_send_media' => true,
                    ],
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    /** Sending the same request twice, the second click of an impatient visitor, adds no second card. */
    public function test_the_same_quote_request_twice_records_one_card(): void
    {
        [$sender, $recipient] = $this->pair();
        $service = $this->soundEngineering($recipient);
        $thread = $this->thread($sender, $recipient);
        $this->quoteOrigin($thread, $sender, $service, ['need' => 'mixing', 'tracks' => 5]);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Je renvoie ma demande',
            'serviceId' => $service->id,
            'quote' => ['need' => 'mixing', 'tracks' => 5],
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals($this->sentMessage($this->latestMessage(), $sender, 'Je renvoie ma demande', null));
        $this->assertSame(1, $this->originCount());
    }

    /** A question after a quote request is still about the same service. */
    public function test_a_question_after_a_quote_request_records_nothing_new(): void
    {
        [$sender, $recipient] = $this->pair();
        $service = $this->soundEngineering($recipient);
        $thread = $this->thread($sender, $recipient);
        $this->quoteOrigin($thread, $sender, $service, ['need' => 'mixing']);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Et le délai ?',
            'serviceId' => $service->id,
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals($this->sentMessage($this->latestMessage(), $sender, 'Et le délai ?', null));
        $this->assertSame(1, $this->originCount());
    }

    /** Asking for a quote after a question, or for another one, is a new reason to talk. */
    public function test_another_quote_request_records_it(): void
    {
        [$sender, $recipient] = $this->pair();
        $service = $this->soundEngineering($recipient);
        $thread = $this->thread($sender, $recipient);
        $this->quoteOrigin($thread, $sender, $service, ['need' => 'mixing']);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Et le master ?',
            'serviceId' => $service->id,
            'quote' => ['need' => 'mastering'],
        ], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals($this->sentMessage($this->latestMessage(), $sender, 'Et le master ?', $this->serviceOrigin($service, ['need' => 'mastering'])));
        $this->assertSame(2, $this->originCount());
    }

    /** The service may be deleted, the conversation still says what was asked. */
    public function test_a_deleted_service_leaves_its_snapshot(): void
    {
        [$sender, $recipient] = $this->pair();
        $service = $this->soundEngineering($recipient);
        $thread = $this->thread($sender, $recipient);
        $message = $this->quoteOrigin($thread, $sender, $service, ['need' => 'live', 'deadline' => '2027-03-01'])->message;
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->remove($service);
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
                        'type' => 'service',
                        'musician_announce_id' => null,
                        'teacher_profile_id' => null,
                        'announce_type' => null,
                        'instruments' => [],
                        'styles' => [],
                        'location_name' => null,
                        'lesson_request' => null,
                        'service_id' => null,
                        'service_kind' => 'sound_engineering',
                        'quote' => ['need' => 'live', 'deadline' => '2027-03-01'],
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
     * @param array<string, int|string>|null $quote
     *
     * @return array<string, mixed>
     */
    private function serviceOrigin(Service $service, ?array $quote): array
    {
        return [
            'type' => 'service',
            'musician_announce_id' => null,
            'teacher_profile_id' => null,
            'announce_type' => null,
            'instruments' => [],
            'styles' => [],
            'location_name' => null,
            'lesson_request' => null,
            'service_id' => (string) $service->id,
            'service_kind' => 'sound_engineering',
            'quote' => $quote,
        ];
    }

    /**
     * The POST response; a message that adds no card carries no contact_origin.
     *
     * @param array<string, mixed>|null $contactOrigin
     *
     * @return array<string, mixed>
     */
    private function sentMessage(Message $message, User $sender, string $content, ?array $contactOrigin): array
    {
        return [
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
            'content' => $content,
            'content_preview' => $content,
            'is_deleted' => false,
            'reactions' => [],
            'editable_content' => $content,
            ...($contactOrigin !== null ? ['contact_origin' => $contactOrigin] : []),
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

    private function soundEngineering(User $user): Service
    {
        return ServiceFactory::new(['user' => $user])->soundEngineering()->create();
    }

    private function thread(User $sender, User $recipient): MessageThread
    {
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $sender])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $recipient])->create();
        MessageThreadMetaFactory::new(['thread' => $thread, 'user' => $sender])->create();

        return $thread;
    }

    /** @param array<string, int|string> $quote */
    private function quoteOrigin(MessageThread $thread, User $author, Service $service, array $quote): MessageContactOrigin
    {
        $message = MessageFactory::new(['thread' => $thread, 'author' => $author, 'content' => 'Bonjour', 'creationDatetime' => new \DateTime('2026-09-01 10:00:00')])->create();
        $thread->lastMessage = $message;
        \Zenstruck\Foundry\Persistence\save($thread);

        return MessageContactOriginFactory::new([
            'message' => $message,
            'type' => ContactOriginType::Service,
            'service' => $service,
            'serviceKind' => ServiceKind::SoundEngineering,
            'quote' => $quote,
            'announceType' => null,
            'instruments' => [],
            'styles' => [],
            'locationName' => null,
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

    private function assertNoMessage(): void
    {
        $this->assertSame(0, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Message::class)->count([]));
    }

    private function originCount(): int
    {
        return self::getContainer()->get(EntityManagerInterface::class)->getRepository(MessageContactOrigin::class)->count([]);
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
