<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

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
class BandSpaceNoteGetTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_item_with_content(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $content = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]]]];
        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'content' => $content,
            'position' => 0,
            'createdBy' => $user,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => $note->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => $content,
            'content_version' => 1,
            'has_children' => false,
            'created_by' => ['id' => $user->id, 'username' => 'base_admin'],
            'emoji' => null,
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'update_datetime' => null,
        ]);
    }

    /**
     * Closing an account keeps the note's author. DeleteAccountProcedure anonymises the fos_user row in
     * place and keeps its primary key, so the FK still resolves, to a deleted_<uuid> handle. The read
     * side substitutes a label, because the raw handle names nobody. The note keeps a byline either
     * way, which is what lets created_by be non-nullable.
     */
    public function test_get_item_labels_the_author_of_a_closed_account(): void
    {
        $reader = UserFactory::new()->asBaseUser()->create();
        // Its own email, because asBaseUser pins a fixed one and the column is unique.
        $departed = UserFactory::new()->asBaseUser()->create([
            'username' => 'deleted_c7c9f2e1',
            'email' => 'deleted_c7c9f2e1@email.com',
            'deletionDatetime' => new \DateTimeImmutable('2024-06-01 09:00:00'),
        ]);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $reader])->create();

        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Note orpheline',
            'position' => 0,
            'createdBy' => $departed,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($reader);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => $note->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Note orpheline',
            'parent_id' => null,
            'position' => 0,
            'content' => null,
            'content_version' => 1,
            'has_children' => false,
            'created_by' => [
                'id' => $departed->id,
                'username' => 'Utilisateur supprimé',
            ],
            'emoji' => null,
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'update_datetime' => null,
        ]);
    }

    /**
     * The read side is where a note is corrupted for good: the editor is seeded from this response
     * once at mount, then autosaves it back a couple of seconds later. So a character mangled here
     * is written to the database on the next keystroke and the note never recovers.
     */
    public function test_get_item_preserves_special_characters(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

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
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'marks' => [['type' => 'italic']], 'text' => 'Balances'],
                        ['type' => 'text', 'text' => ' '],
                        ['type' => 'text', 'text' => 'à 18h'],
                    ],
                ],
            ],
        ];
        $note = BandSpaceNoteFactory::new([
            'createdBy' => $user,
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'content' => $content,
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => $note->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'My Note',
            'parent_id' => null,
            'position' => 0,
            'content' => $content,
            'content_version' => 1,
            'has_children' => false,
            'created_by' => ['id' => $user->id, 'username' => $user->username],
            'emoji' => null,
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'update_datetime' => null,
        ]);
    }

    public function test_get_item_dangerous_uri_attributes_are_neutralised(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $content = [
            'type' => 'doc',
            'content' => [
                ['type' => 'image', 'attrs' => ['src' => 'javascript:alert(1)']],
                [
                    'type' => 'paragraph',
                    'content' => [[
                        'type' => 'text',
                        'marks' => [['type' => 'link', 'attrs' => ['href' => 'vbscript:msgbox(1)']]],
                        'text' => 'Cliquez ici',
                    ]],
                ],
            ],
        ];
        $note = BandSpaceNoteFactory::new([
            'createdBy' => $user,
            'bandSpace' => $bandSpace,
            'title' => 'My Note',
            'content' => $content,
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceNote',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            '@type' => 'BandSpaceNote',
            'id' => $note->id,
            'band_space_id' => $bandSpace->id,
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
                            'marks' => [],
                            'text' => 'Cliquez ici',
                        ]],
                    ],
                ],
            ],
            'content_version' => 1,
            'has_children' => false,
            'created_by' => ['id' => $user->id, 'username' => $user->username],
            'emoji' => null,
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'update_datetime' => null,
        ]);
    }

    public function test_get_item_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes/nonexistent-id');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_get_item_unauthenticated(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'title' => 'Note'])->create();

        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_get_item_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'title' => 'Secret Note'])->create();

        $this->client->loginUser($otherUser);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);

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

    public function test_get_item_inactive_member(): void
    {
        $inactiveUser = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $inactiveUser,
            'status' => MembershipStatus::Left,
        ])->create();

        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'title' => 'Secret Note'])->create();

        $this->client->loginUser($inactiveUser);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
