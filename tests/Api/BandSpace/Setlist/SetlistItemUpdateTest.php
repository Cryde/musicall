<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\SetlistItemType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\SetlistItemRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SetlistItemFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\BandSpace\Setlist\ValidSetlistItemPayload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SetlistItemUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_update_item_fields(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $item = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Talk,
            'label' => 'Old label',
            'durationOverride' => 30,
            'position' => 0,
        ])->create();
        $itemId = (string) $item->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $itemId,
            ['label' => 'Updated label', 'duration_override' => 60, 'note' => 'pace it'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $refreshed = self::getContainer()->get(SetlistItemRepository::class)->find($itemId);
        $this->assertSame('Updated label', $refreshed->label);
        $this->assertSame(60, $refreshed->durationOverride);
        $this->assertSame('pace it', $refreshed->note);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/SetlistItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $itemId,
            '@type' => 'SetlistItem',
            'id' => $itemId,
            'band_space_id' => (string) $bandSpace->id,
            'setlist_id' => (string) $setlist->id,
            'type' => 'talk',
            'song' => null,
            'label' => 'Updated label',
            'duration_override' => 60,
            'note' => 'pace it',
            'transition' => null,
            'position' => 0,
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Setlist, (string) $setlist->id);
        $this->assertCount(1, $activities);
        $this->assertSame('setlist_item_updated', $activities[0]->type);
    }

    public function test_update_item_cross_setlist_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $setlistA = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $setlistB = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $itemInB = SetlistItemFactory::new([
            'setlist' => $setlistB,
            'type' => SetlistItemType::Talk,
            'label' => 'B',
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlistA->id . '/items/' . $itemInB->id,
            ['label' => 'X'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Item introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Item introuvable',
        ]);
    }

    public function test_update_item_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $item = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Talk,
            'label' => 'L',
            'position' => 0,
        ])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $item->id,
            ['label' => 'Hacked'],
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

    /**
     * The label is the only thing that names a pause or an MC slot. Emptying it left a row showing
     * a bare dash in the editor, in live mode and on the printed sheet, and since the type and the
     * song are immutable the only way back was to delete and recreate, losing the position, the
     * note and the transition. Creation already refused it, the update now does too.
     */
    public function test_clearing_the_label_of_a_break_item_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $item = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Break,
            'label' => 'Pause',
            'position' => 0,
        ])->create();
        $itemId = (string) $item->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $itemId,
            ['label' => null],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
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

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(SetlistItemRepository::class)->find($itemId);
        $this->assertSame('Pause', $stored?->label);
    }

    /** Whitespace is not a label: the row would still print as a blank line. */
    public function test_blanking_the_label_of_a_talk_item_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $item = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Talk,
            'label' => 'Présentation du groupe',
            'position' => 0,
        ])->create();
        $itemId = (string) $item->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $itemId,
            ['label' => '   '],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
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

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(SetlistItemRepository::class)->find($itemId);
        $this->assertSame('Présentation du groupe', $stored?->label);
    }

    /** The other half of the same rule, mirrored from creation: a song row is named by its song. */
    public function test_labelling_a_song_item_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Le titre'])->create();

        $item = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Song,
            'song' => $song,
            'label' => null,
            'position' => 0,
        ])->create();
        $itemId = (string) $item->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $itemId,
            ['label' => 'Un autre nom'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
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

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(SetlistItemRepository::class)->find($itemId);
        $this->assertNull($stored?->label);
    }

    /**
     * A song row keeps its null label untouched while the other fields change: the new rule must
     * not turn every partial patch of a song item into a 422.
     */
    public function test_updating_a_song_item_without_touching_the_label_still_works(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Le titre'])->create();

        $item = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Song,
            'song' => $song,
            'label' => null,
            'position' => 0,
        ])->create();
        $itemId = (string) $item->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $itemId,
            ['duration_override' => 210, 'note' => 'capo 2'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SetlistItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $itemId,
            '@type' => 'SetlistItem',
            'id' => $itemId,
            'band_space_id' => (string) $bandSpace->id,
            'setlist_id' => (string) $setlist->id,
            'type' => 'song',
            'song' => [
                'id' => (string) $song->id,
                'title' => 'Le titre',
                'tempo' => null,
                'tonality' => null,
                'reference_duration' => null,
                'archive_datetime' => null,
                '@type' => 'SetlistItemSongInfo',
            ],
            'label' => null,
            'duration_override' => 210,
            'note' => 'capo 2',
            'transition' => null,
            'position' => 0,
        ]);
    }
}
