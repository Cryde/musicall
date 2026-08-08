<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use App\Tests\Factory\User\UserFactory;
use App\Enum\BandSpace\MembershipStatus;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceNoteUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_update_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Old Title',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['title' => 'New Title'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'New Title',
            'parent_id' => null,
            'position' => 0,
            'content' => null,
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Notes, $note->id);
        $this->assertCount(1, $activities);
        $this->assertSame('note_renamed', $activities[0]->type);
        $this->assertSame(['from' => 'Old Title', 'to' => 'New Title'], $activities[0]->payload);
    }

    public function test_update_content(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $content = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Updated content']]]]];

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['content' => $content],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => $content,
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Notes, $note->id);
        $this->assertCount(1, $activities);
        $this->assertSame('note_content_updated', $activities[0]->type);
        $this->assertNull($activities[0]->payload);
    }

    public function test_update_position(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['position' => 5],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 5,
            'content' => null,
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Notes, $note->id);
        $this->assertCount(0, $activities);
    }

    public function test_update_emoji(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['emoji' => '🎵'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => null,
            'has_children' => false,
            'emoji' => '🎵',
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Notes, $note->id);
        $this->assertCount(1, $activities);
        $this->assertSame('note_emoji_changed', $activities[0]->type);
        $this->assertSame(['from' => null, 'to' => '🎵'], $activities[0]->payload);
    }

    public function test_update_emoji_to_null(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'emoji' => '🎵',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            [],
            [],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
            json_encode(['emoji' => null])
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => null,
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_update_content_to_null(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $content = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Some content']]]]];
        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'content' => $content,
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            [],
            [],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
            json_encode(['content' => null])
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => null,
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_update_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/nonexistent-id',
            ['title' => 'New Title'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_update_empty_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Original Title',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['title' => ''],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'title',
                    'message' => 'Veuillez spécifier un titre',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'title: Veuillez spécifier un titre',
            'description' => 'title: Veuillez spécifier un titre',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
        ]);
    }

    public function test_update_title_too_long(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Original Title',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['title' => str_repeat('a', 256)],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'title',
                    'message' => 'Le titre ne peut pas dépasser 255 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
            ],
            'detail' => 'title: Le titre ne peut pas dépasser 255 caractères',
            'description' => 'title: Le titre ne peut pas dépasser 255 caractères',
            'type' => '/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'title' => 'An error occurred',
        ]);
    }

    /**
     * A text node is prose, not markup: TipTap renders it as a DOM text node, so text that looks like
     * a tag stays inert without any escaping. Running it through an HtmlSanitizer used to entity
     * encode it into the note itself, which is what this pins against coming back.
     */
    public function test_update_content_keeps_markup_looking_text_verbatim(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'position' => 0,
        ])->create();

        $content = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => '<script>alert(1)</script>'],
                        ['type' => 'text', 'text' => '<img src=x onerror="alert(1)">'],
                        ['type' => 'text', 'text' => 'Safe text'],
                    ],
                ],
            ],
        ];

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['content' => $content],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => $content,
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Every character an HTML entity encoder touches, in one French sentence: the apostrophe above
     * all, since in this app it appears in most notes ever written.
     */
    public function test_update_content_preserves_special_characters(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'position' => 0,
        ])->create();

        $content = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [[
                        'type' => 'text',
                        'text' => 'C\'est l\'heure de la répét « Rock & Roll » : écrire à contact@salle.fr, 2+2=4, et on dit "oui" à 100 %',
                    ]],
                ],
            ],
        ];

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['content' => $content],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => $content,
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * TipTap emits a standalone space as its own text node whenever a bold run is followed by one.
     * A sanitiser returned that node as an empty string, which glued the two words together.
     */
    public function test_update_content_preserves_whitespace_only_text_node(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'position' => 0,
        ])->create();

        $content = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Répétition'],
                        ['type' => 'text', 'text' => ' '],
                        ['type' => 'text', 'text' => 'samedi'],
                    ],
                ],
            ],
        ];

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['content' => $content],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => $content,
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    /** A sanitiser stopped at its 20000 byte input budget and silently returned the rest as nothing. */
    public function test_update_content_preserves_a_text_node_over_twenty_thousand_bytes(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'position' => 0,
        ])->create();

        // Two bytes per character, so 15000 of them is comfortably past the budget.
        $longText = str_repeat('é', 15000);
        $content = [
            'type' => 'doc',
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $longText]]]],
        ];

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['content' => $content],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => $content,
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    /** The link goes, the words stay: a member still reads what they typed. */
    public function test_update_content_drops_link_mark_with_dangerous_href(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'position' => 0,
        ])->create();

        $content = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [[
                        'type' => 'text',
                        'marks' => [
                            ['type' => 'bold'],
                            ['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']],
                        ],
                        'text' => 'Cliquez ici',
                    ]],
                ],
            ],
        ];

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['content' => $content],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [[
                            'type' => 'text',
                            'marks' => [['type' => 'bold']],
                            'text' => 'Cliquez ici',
                        ]],
                    ],
                ],
            ],
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * An image is nothing without its src, so the node goes rather than being emptied. `data:` counts
     * as dangerous here because the editor sets `allowBase64: false`, so no note ever holds one.
     */
    public function test_update_content_drops_image_node_with_dangerous_src(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'position' => 0,
        ])->create();

        $content = [
            'type' => 'doc',
            'content' => [
                ['type' => 'image', 'attrs' => ['src' => 'javascript:alert(1)']],
                ['type' => 'image', 'attrs' => ['src' => 'data:image/svg+xml;base64,PHN2Zz4=']],
                ['type' => 'image', 'attrs' => ['src' => '//evil.example/tracker.png']],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Après']]],
            ],
        ];

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['content' => $content],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Après']]],
                ],
            ],
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * A backslash after the leading slash is the same attack as `//evil.example` wearing a disguise:
     * the WHATWG URL parser normalises it to a slash, so a browser resolves `/\evil.example/x.png`
     * against a different host entirely while the value still reads as an internal path. A member
     * could otherwise point a note image at any host and beacon every reader of the note.
     */
    public function test_update_content_drops_backslash_protocol_relative_uris(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'position' => 0,
        ])->create();

        $content = [
            'type' => 'doc',
            'content' => [
                ['type' => 'image', 'attrs' => ['src' => '/\\evil.example/tracker.png']],
                ['type' => 'image', 'attrs' => ['src' => '/\\/evil.example/tracker.png']],
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Après',
                            'marks' => [['type' => 'link', 'attrs' => ['href' => '/\\evil.example']]],
                        ],
                    ],
                ],
            ],
        ];

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['content' => $content],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Après', 'marks' => []]]],
                ],
            ],
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * `content` is an untyped JSON column, so a caller can put a shape there the editor would never
     * write. A non string `src` used to skip the allowlist entirely on its `is_string` guard.
     */
    public function test_update_content_drops_a_non_string_uri_attribute(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'position' => 0,
        ])->create();

        $content = [
            'type' => 'doc',
            'content' => [
                ['type' => 'image', 'attrs' => ['src' => ['javascript:alert(1)']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Après']]],
            ],
        ];

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['content' => $content],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Après']]],
                ],
            ],
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * The other half of the allowlist: an uploaded note image is a same origin path and a typed link
     * is http(s) or mailto, so all three have to survive untouched.
     */
    public function test_update_content_keeps_note_image_path_and_safe_links(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'position' => 0,
        ])->create();

        $imageSrc = '/api/band_spaces/' . $bandSpace->id . '/files/11111111-1111-1111-1111-111111111111/versions/2/download';
        $content = [
            'type' => 'doc',
            'content' => [
                ['type' => 'image', 'attrs' => ['src' => $imageSrc, 'alt' => null, 'title' => null]],
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://musicall.com/salles?ville=lyon#plan']]],
                            'text' => 'la salle',
                        ],
                        [
                            'type' => 'text',
                            'marks' => [['type' => 'link', 'attrs' => ['href' => 'mailto:contact@salle.fr']]],
                            'text' => 'écrire',
                        ],
                    ],
                ],
            ],
        ];

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['content' => $content],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $noteRepository = self::getContainer()->get(BandSpaceNoteRepository::class);
        $refreshed = $noteRepository->find($note->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => (string) $note->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => $content,
            'has_children' => false,
            'emoji' => null,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_update_negative_position(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['position' => -1],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/ea4e51d1-3342-48bd-87f1-9e672cd90cad',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'position',
                    'message' => 'La position doit être positive ou zéro',
                    'code' => 'ea4e51d1-3342-48bd-87f1-9e672cd90cad',
                ],
            ],
            'detail' => 'position: La position doit être positive ou zéro',
            'description' => 'position: La position doit être positive ou zéro',
            'type' => '/validation_errors/ea4e51d1-3342-48bd-87f1-9e672cd90cad',
            'title' => 'An error occurred',
        ]);
    }

    public function test_update_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
        ])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['title' => 'Hacked'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
        ]);
    }

    public function test_update_inactive_member(): void
    {
        $inactiveUser = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $inactiveUser,
            'status' => MembershipStatus::Left,
        ])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
        ])->create();

        $this->client->loginUser($inactiveUser);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['title' => 'Hacked'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_update_note_from_other_band_space(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $otherBandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherBandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $otherBandSpace,
            'title' => 'Note in other space',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['title' => 'Hacked'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_update_unauthenticated(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'title' => 'Note', 'position' => 0])->create();

        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            ['title' => 'Hacked'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
