<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\SetlistItemRepository;
use App\Tests\ApiTestAssertionsTrait;
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
}
