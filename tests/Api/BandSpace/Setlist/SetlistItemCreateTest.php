<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\SetlistItemRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Repository\BandSpace\SetlistRepository;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\BandSpace\Setlist\ValidSetlistItemPayload;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SetlistItemCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_create_song_item(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $song = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Song X',
            'tempo' => 120,
            'tonality' => 'C',
            'referenceDuration' => 200,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items',
            ['type' => 'song', 'song_id' => (string) $song->id],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $items = self::getContainer()->get(SetlistItemRepository::class)->findBy(['setlist' => $setlist->id]);
        $this->assertCount(1, $items);
        $item = $items[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/SetlistItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $item->id,
            '@type' => 'SetlistItem',
            'id' => (string) $item->id,
            'band_space_id' => (string) $bandSpace->id,
            'setlist_id' => (string) $setlist->id,
            'type' => 'song',
            'song' => [
                'id' => (string) $song->id,
                'title' => 'Song X',
                'tempo' => 120,
                'tonality' => 'C',
                'reference_duration' => 200,
                'has_lyrics' => false,
                'archive_datetime' => null,
                '@type' => 'SetlistItemSongInfo',
            ],
            'label' => null,
            'duration_override' => null,
            'note' => null,
            'transition' => null,
            'position' => 0,
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Setlist, (string) $setlist->id);
        $this->assertCount(1, $activities);
        $this->assertSame('setlist_item_added', $activities[0]->type);

        // Its running order is what a setlist is, so this counts as editing it (#1046).
        $this->assertNotNull(self::getContainer()->get(SetlistRepository::class)->find((string) $setlist->id)?->updateDatetime);
    }

    public function test_create_interlude_item(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items',
            ['type' => 'interlude', 'label' => 'Drum solo', 'duration_override' => 45],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $items = self::getContainer()->get(SetlistItemRepository::class)->findBy(['setlist' => $setlist->id]);
        $this->assertCount(1, $items);
        $item = $items[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/SetlistItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $item->id,
            '@type' => 'SetlistItem',
            'id' => (string) $item->id,
            'band_space_id' => (string) $bandSpace->id,
            'setlist_id' => (string) $setlist->id,
            'type' => 'interlude',
            'song' => null,
            'label' => 'Drum solo',
            'duration_override' => 45,
            'note' => null,
            'transition' => null,
            'position' => 0,
        ]);
    }

    public function test_create_song_item_without_song_id_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items',
            ['type' => 'song'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidSetlistItemPayload::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'song_id',
                    'message' => "Un song_id est requis pour un item de type 'song'",
                    'code' => ValidSetlistItemPayload::ERROR_CODE,
                ],
            ],
            'detail' => "song_id: Un song_id est requis pour un item de type 'song'",
            'type' => '/validation_errors/' . ValidSetlistItemPayload::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => "song_id: Un song_id est requis pour un item de type 'song'",
        ]);
    }

    public function test_create_song_item_with_label_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items',
            ['type' => 'song', 'song_id' => (string) $song->id, 'label' => 'extra'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidSetlistItemPayload::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'label',
                    'message' => "Le champ label n'est pas autorisé pour un item de type 'song'",
                    'code' => ValidSetlistItemPayload::ERROR_CODE,
                ],
            ],
            'detail' => "label: Le champ label n'est pas autorisé pour un item de type 'song'",
            'type' => '/validation_errors/' . ValidSetlistItemPayload::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => "label: Le champ label n'est pas autorisé pour un item de type 'song'",
        ]);
    }

    public function test_create_talk_item_without_label_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items',
            ['type' => 'talk'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidSetlistItemPayload::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'label',
                    'message' => "Un libellé est requis pour ce type d'item",
                    'code' => ValidSetlistItemPayload::ERROR_CODE,
                ],
            ],
            'detail' => "label: Un libellé est requis pour ce type d'item",
            'type' => '/validation_errors/' . ValidSetlistItemPayload::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => "label: Un libellé est requis pour ce type d'item",
        ]);
    }

    public function test_create_break_item_with_song_id_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items',
            ['type' => 'break', 'label' => 'Pause', 'song_id' => (string) $song->id],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidSetlistItemPayload::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'song_id',
                    'message' => "Le champ song_id n'est autorisé que pour un item de type 'song'",
                    'code' => ValidSetlistItemPayload::ERROR_CODE,
                ],
            ],
            'detail' => "song_id: Le champ song_id n'est autorisé que pour un item de type 'song'",
            'type' => '/validation_errors/' . ValidSetlistItemPayload::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => "song_id: Le champ song_id n'est autorisé que pour un item de type 'song'",
        ]);
    }

    public function test_create_song_item_cross_band_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();

        $setlist = SetlistFactory::new(['bandSpace' => $myBand])->create();
        $songInOtherBand = SongFactory::new(['bandSpace' => $otherBand])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $myBand->id . '/setlists/' . $setlist->id . '/items',
            ['type' => 'song', 'song_id' => (string) $songInOtherBand->id],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "La chanson référencée n'appartient pas à ce Band Space",
            'status' => 422,
            'type' => '/errors/422',
            'description' => "La chanson référencée n'appartient pas à ce Band Space",
        ]);
    }

    public function test_create_item_assigns_next_position(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        \App\Tests\Factory\BandSpace\SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => \App\Enum\BandSpace\SetlistItemType::Talk,
            'label' => 'Existing 0',
            'position' => 0,
        ])->create();
        \App\Tests\Factory\BandSpace\SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => \App\Enum\BandSpace\SetlistItemType::Talk,
            'label' => 'Existing 1',
            'position' => 1,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items',
            ['type' => 'talk', 'label' => 'New'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $items = self::getContainer()->get(SetlistItemRepository::class)
            ->findBy(['setlist' => $setlist->id], ['position' => 'ASC']);
        $this->assertCount(3, $items);
        $this->assertSame(2, $items[2]->position);
        $this->assertSame('New', $items[2]->label);
    }

    public function test_create_item_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items',
            ['type' => 'talk', 'label' => 'Hi'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
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
    /**
     * A song archived after a setlist was built keeps rendering in the items that already point at
     * it, so the finder returns archived rows and the create path saw nothing wrong with a fresh
     * reference to one. The picker never offers an archived song, so this closes the direct call
     * only: "retired from the repertoire" now means the same thing to the API and to the interface.
     */
    public function test_create_song_item_referencing_an_archived_song_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $song = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Retirée du répertoire',
            'archiveDatetime' => new \DateTimeImmutable('2026-06-13T09:00:00+00:00'),
        ])->create();
        $setlistId = (string) $setlist->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlistId . '/items',
            ['type' => 'song', 'song_id' => (string) $song->id],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cette chanson est archivée, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cette chanson est archivée, les modifications sont désactivées',
        ]);

        $this->assertCount(
            0,
            self::getContainer()->get(SetlistItemRepository::class)->findBy(['setlist' => $setlistId]),
        );
    }

    /** « Insérer ici » (#1061): the new item takes that place and the rest move down, gaps closed. */
    public function test_create_item_at_a_position_renumbers_the_running_order(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        // A gap at 1, left by an earlier removal.
        foreach (['Premier' => 0, 'Deuxième' => 2, 'Troisième' => 3] as $label => $position) {
            \App\Tests\Factory\BandSpace\SetlistItemFactory::new([
                'setlist' => $setlist,
                'type' => \App\Enum\BandSpace\SetlistItemType::Talk,
                'label' => $label,
                'position' => $position,
            ])->create();
        }

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items',
            ['type' => 'break', 'label' => 'Pause', 'position' => 1],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class)->clear();
        $items = self::getContainer()->get(SetlistItemRepository::class)
            ->findBy(['setlist' => $setlist->id], ['position' => 'ASC']);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SetlistItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $items[1]->id,
            '@type' => 'SetlistItem',
            'id' => (string) $items[1]->id,
            'band_space_id' => (string) $bandSpace->id,
            'setlist_id' => (string) $setlist->id,
            'type' => 'break',
            'song' => null,
            'label' => 'Pause',
            'duration_override' => null,
            'note' => null,
            'transition' => null,
            'position' => 1,
        ]);
        $this->assertSame(
            [['Premier', 0], ['Pause', 1], ['Deuxième', 2], ['Troisième', 3]],
            array_map(static fn ($item): array => [$item->label, $item->position], $items),
        );
    }

    public function test_create_item_past_the_end_goes_last(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        \App\Tests\Factory\BandSpace\SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => \App\Enum\BandSpace\SetlistItemType::Talk,
            'label' => 'Seul',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items',
            ['type' => 'talk', 'label' => 'Merci', 'position' => 40],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $created = self::getContainer()->get(SetlistItemRepository::class)->findOneBy(['setlist' => $setlist->id, 'label' => 'Merci']);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SetlistItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $created->id,
            '@type' => 'SetlistItem',
            'id' => (string) $created->id,
            'band_space_id' => (string) $bandSpace->id,
            'setlist_id' => (string) $setlist->id,
            'type' => 'talk',
            'song' => null,
            'label' => 'Merci',
            'duration_override' => null,
            'note' => null,
            'transition' => null,
            'position' => 1,
        ]);
    }

    public function test_create_item_at_a_negative_position_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items',
            ['type' => 'talk', 'label' => 'Merci', 'position' => -1],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
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
            'type' => '/validation_errors/ea4e51d1-3342-48bd-87f1-9e672cd90cad',
            'title' => 'An error occurred',
            'description' => 'position: La position doit être positive ou zéro',
        ]);
    }
}
