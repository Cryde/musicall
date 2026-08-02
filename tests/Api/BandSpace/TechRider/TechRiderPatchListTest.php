<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderItem;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\TechRiderItemType;
use App\Enum\BandSpace\TechRiderPatchDirection;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\TechRiderPatchRowRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceActivityFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\BandSpace\TechRiderItemFactory;
use App\Tests\Factory\BandSpace\TechRiderPatchRowFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\BandSpace\TechRider\TechRiderPatchRows;
use DateTime;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A patch list is the page a sound engineer reads first: which source lands on which channel,
 * with which microphone, routed where. It is saved as a whole grid, so the failure mode worth
 * most of this file is a refused save leaving a band with half of a 24 row list.
 */
#[ResetDatabase]
class TechRiderPatchListTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_saving_a_patch_list_persists_both_directions_in_array_order(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'inputs' => [
                ['channel' => 1, 'name' => 'KICK IN', 'microphone' => 'Beta 91', 'routing' => 'Mic split A1', 'colour' => 'red'],
                ['channel' => 10, 'name' => 'BASS DI', 'microphone' => 'DI Out', 'routing' => 'Mic split B2', 'colour' => 'cyan'],
            ],
            'outputs' => [
                ['channel' => 1, 'name' => 'WEDGE CHANT', 'microphone' => null, 'routing' => 'Retour 1', 'colour' => null],
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $rows = self::getContainer()->get(TechRiderPatchRowRepository::class)->findByItem($item);
        $this->assertSame(
            [
                ['input', 0, 1, 'KICK IN', 'Beta 91', 'Mic split A1', 'red'],
                ['input', 1, 10, 'BASS DI', 'DI Out', 'Mic split B2', 'cyan'],
                ['output', 0, 1, 'WEDGE CHANT', null, 'Retour 1', null],
            ],
            array_map(static fn ($row): array => [
                $row->direction->value,
                $row->position,
                $row->channel,
                $row->name,
                $row->microphone,
                $row->routing,
                $row->colour?->value,
            ], $rows),
        );
    }

    /**
     * The response is the item, so the client that just saved a grid gets the canonical one back
     * (row ids, positions and the hex to paint with) without a second request.
     */
    public function test_the_response_carries_the_saved_grid_inline_on_the_item(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'inputs' => [['channel' => 3, 'name' => 'SNARE', 'microphone' => 'SM57', 'routing' => 'A3', 'colour' => 'green']],
            'outputs' => [],
        ]);

        $this->assertResponseIsSuccessful();

        $rows = self::getContainer()->get(TechRiderPatchRowRepository::class)->findByItem($item);
        $this->assertCount(1, $rows);

        // Asserted outright, not just echoed into the expected body below: $item is the same
        // managed instance the request mutated, so `update_datetime => $item->updateDatetime`
        // would match itself and pass even if the save stopped touching the timestamp.
        $this->assertNotNull($item->updateDatetime);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderItem',
            '@id' => $this->itemUrl($bandSpace, $rider, $item),
            '@type' => 'TechRiderItem',
            'id' => (string) $item->id,
            'band_space_id' => (string) $bandSpace->id,
            'rider_id' => (string) $rider->id,
            'type' => 'patch_list',
            'is_included' => true,
            'title' => 'Patch list',
            'content' => null,
            'file' => null,
            'patch_list' => [
                'inputs' => [
                    [
                        'id' => (string) $rows[0]->id,
                        'channel' => 3,
                        'name' => 'SNARE',
                        'microphone' => 'SM57',
                        'routing' => 'A3',
                        'colour' => 'green',
                        'colour_hex' => '#16a34a',
                        'position' => 0,
                    ],
                ],
                'outputs' => [],
            ],
            'contacts' => null,
            'position' => 0,
            'creation_datetime' => $item->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $item->updateDatetime?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * A save replaces, it does not append. Without this, saving an edited grid would leave the
     * band with both versions interleaved.
     *
     * The earlier list is seeded rather than saved through the API, because loginUser() only
     * survives one request. That also makes this a stronger test: it does not rest on a first
     * save having worked.
     */
    public function test_a_save_replaces_the_stored_list_rather_than_appending_to_it(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $this->seedRows($item, [['input', 1, 'Ancien'], ['input', 2, 'Autre ancien'], ['output', 1, 'Ancien retour']]);

        $this->put($user, $bandSpace, $rider, $item, [
            'inputs' => [['channel' => 5, 'name' => 'Nouveau']],
            'outputs' => [],
        ]);

        $this->assertResponseIsSuccessful();

        $rows = self::getContainer()->get(TechRiderPatchRowRepository::class)->findByItem($item);
        $this->assertSame(
            [['input', 5, 'Nouveau']],
            array_map(
                static fn ($row): array => [$row->direction->value, $row->channel, $row->name],
                $rows,
            ),
        );
    }

    /**
     * Position comes from the order rows arrive in, never from what the client labels them.
     *
     * A JSON object with numeric keys deserialises into the same array property that a JSON
     * array does, so without normalising, these keys would become the stored positions. Probed
     * before it was fixed: the row below landed at position 5.
     */
    public function test_object_keys_in_the_payload_do_not_become_positions(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->client->loginUser($user);
        $this->client->request(
            'PUT',
            $this->itemUrl($bandSpace, $rider, $item) . '/patch_list',
            [],
            [],
            self::HEADERS,
            '{"inputs": {"5": {"channel": 9, "name": "KICK"}}, "outputs": []}',
        );

        $this->assertResponseIsSuccessful();

        $rows = self::getContainer()->get(TechRiderPatchRowRepository::class)->findByItem($item);
        $this->assertSame([[9, 0]], array_map(
            static fn ($row): array => [$row->channel, $row->position],
            $rows,
        ));
    }

    /** Clearing a list the band no longer needs is a save, not a delete of the item. */
    public function test_empty_arrays_clear_both_lists(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $this->seedRows($item, [['input', 1, 'KICK'], ['output', 1, 'WEDGE']]);

        $this->put($user, $bandSpace, $rider, $item, ['inputs' => [], 'outputs' => []]);

        $this->assertResponseIsSuccessful();
        $this->assertSame([], self::getContainer()->get(TechRiderPatchRowRepository::class)->findByItem($item));
    }

    /**
     * The failure mode this endpoint exists to avoid. Validation runs before the processor, so a
     * refused save cannot have deleted anything, and that is asserted by reading the list back
     * rather than trusted from the ordering of the code.
     */
    public function test_a_refused_save_leaves_the_previously_saved_list_intact(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $this->seedRows($item, [['input', 1, 'KICK IN'], ['input', 2, 'SNARE'], ['output', 1, 'WEDGE']]);

        $this->put($user, $bandSpace, $rider, $item, [
            'inputs' => [['channel' => 7, 'name' => 'Valide'], ['channel' => 7, 'name' => 'Doublon']],
            'outputs' => [],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderPatchRows::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'inputs',
                    'message' => 'Le numéro de canal 7 apparaît plusieurs fois',
                    'code' => TechRiderPatchRows::ERROR_CODE,
                ],
            ],
            'detail' => 'inputs: Le numéro de canal 7 apparaît plusieurs fois',
            'type' => '/validation_errors/' . TechRiderPatchRows::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'inputs: Le numéro de canal 7 apparaît plusieurs fois',
        ]);

        $rows = self::getContainer()->get(TechRiderPatchRowRepository::class)->findByItem($item);
        $this->assertSame(
            [['input', 1, 'KICK IN'], ['input', 2, 'SNARE'], ['output', 1, 'WEDGE']],
            array_map(
                static fn ($row): array => [$row->direction->value, $row->channel, $row->name],
                $rows,
            ),
        );
    }

    /**
     * Uniqueness is per direction. A bass DI on input 10 and a wedge on output 10 are different
     * things, and refusing that would make a normal list unsaveable.
     */
    public function test_the_same_channel_number_in_both_directions_is_accepted(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'inputs' => [['channel' => 10, 'name' => 'BASS DI']],
            'outputs' => [['channel' => 10, 'name' => 'WEDGE BASSE']],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, self::getContainer()->get(TechRiderPatchRowRepository::class)->findByItem($item));
    }

    /** The cap itself is allowed: a 64 channel rack is the case the limit was sized for. */
    public function test_exactly_the_cap_in_one_direction_is_accepted(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $rows = [];
        for ($channel = 1; $channel <= TechRiderPatchRows::MAX_ROWS_PER_DIRECTION; $channel++) {
            $rows[] = ['channel' => $channel, 'name' => 'Canal ' . $channel];
        }

        $this->put($user, $bandSpace, $rider, $item, ['inputs' => $rows, 'outputs' => []]);

        $this->assertResponseIsSuccessful();
        $this->assertCount(
            TechRiderPatchRows::MAX_ROWS_PER_DIRECTION,
            self::getContainer()->get(TechRiderPatchRowRepository::class)->findByItem($item),
        );
    }

    public function test_more_rows_than_the_cap_in_one_direction_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $rows = [];
        for ($channel = 1; $channel <= TechRiderPatchRows::MAX_ROWS_PER_DIRECTION + 1; $channel++) {
            $rows[] = ['channel' => $channel, 'name' => 'Canal ' . $channel];
        }

        $this->put($user, $bandSpace, $rider, $item, ['inputs' => $rows, 'outputs' => []]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderPatchRows::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'inputs',
                    'message' => 'Une liste ne peut pas dépasser 64 lignes',
                    'code' => TechRiderPatchRows::ERROR_CODE,
                ],
            ],
            'detail' => 'inputs: Une liste ne peut pas dépasser 64 lignes',
            'type' => '/validation_errors/' . TechRiderPatchRows::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'inputs: Une liste ne peut pas dépasser 64 lignes',
        ]);
    }

    public function test_a_channel_outside_the_allowed_range_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'inputs' => [['channel' => 0, 'name' => 'Canal zéro']],
            'outputs' => [],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderPatchRows::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'inputs[0].channel',
                    'message' => 'Le numéro de canal doit être compris entre 1 et 999',
                    'code' => TechRiderPatchRows::ERROR_CODE,
                ],
            ],
            'detail' => 'inputs[0].channel: Le numéro de canal doit être compris entre 1 et 999',
            'type' => '/validation_errors/' . TechRiderPatchRows::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'inputs[0].channel: Le numéro de canal doit être compris entre 1 et 999',
        ]);
    }

    public function test_an_unknown_colour_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'inputs' => [['channel' => 1, 'name' => 'KICK', 'colour' => 'chartreuse']],
            'outputs' => [],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderPatchRows::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'inputs[0].colour',
                    'message' => 'Couleur inconnue',
                    'code' => TechRiderPatchRows::ERROR_CODE,
                ],
            ],
            'detail' => 'inputs[0].colour: Couleur inconnue',
            'type' => '/validation_errors/' . TechRiderPatchRows::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'inputs[0].colour: Couleur inconnue',
        ]);
    }

    public function test_an_over_length_field_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'inputs' => [['channel' => 1, 'routing' => str_repeat('é', TechRiderPatchRows::MAX_ROUTING_LENGTH + 1)]],
            'outputs' => [],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderPatchRows::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'inputs[0].routing',
                    'message' => 'Ce champ ne peut pas dépasser 180 caractères',
                    'code' => TechRiderPatchRows::ERROR_CODE,
                ],
            ],
            'detail' => 'inputs[0].routing: Ce champ ne peut pas dépasser 180 caractères',
            'type' => '/validation_errors/' . TechRiderPatchRows::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'inputs[0].routing: Ce champ ne peut pas dépasser 180 caractères',
        ]);
    }

    /**
     * A field the server does not know is refused rather than dropped. `mic` instead of
     * `microphone` would otherwise be answered with a success and a silently empty column.
     */
    public function test_an_unknown_field_in_a_row_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'inputs' => [['channel' => 1, 'mic' => 'SM58']],
            'outputs' => [],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderPatchRows::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'inputs[0]',
                    'message' => 'Chaque ligne doit être un objet avec un numéro de canal entier',
                    'code' => TechRiderPatchRows::ERROR_CODE,
                ],
            ],
            'detail' => 'inputs[0]: Chaque ligne doit être un objet avec un numéro de canal entier',
            'type' => '/validation_errors/' . TechRiderPatchRows::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'inputs[0]: Chaque ligne doit être un objet avec un numéro de canal entier',
        ]);
    }

    /**
     * A grid on a text item is refused rather than stored. It would be a body nothing renders,
     * and the type is what every reader branches on.
     */
    public function test_saving_a_patch_list_on_another_item_type_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $textItem = TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::Text,
            'title' => 'Sonorisation',
            'position' => 0,
        ])->create();

        $this->put($user, $bandSpace, $rider, $textItem, [
            'inputs' => [['channel' => 1, 'name' => 'KICK']],
            'outputs' => [],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Seul un élément de type patch list peut contenir une patch list',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Seul un élément de type patch list peut contenir une patch list',
        ]);
    }

    public function test_saving_onto_an_item_of_another_rider_is_not_found(): void
    {
        [$user, $bandSpace, $rider] = $this->seed();
        $otherRider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $theirItem = TechRiderItemFactory::new([
            'techRider' => $otherRider,
            'type' => TechRiderItemType::PatchList,
            'title' => 'Ailleurs',
            'position' => 0,
        ])->create();

        $this->put($user, $bandSpace, $rider, $theirItem, [
            'inputs' => [['channel' => 1, 'name' => 'KICK']],
            'outputs' => [],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Élément introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Élément introuvable',
        ]);

        $this->assertSame([], self::getContainer()->get(TechRiderPatchRowRepository::class)->findByItem($theirItem));
    }

    public function test_saving_a_patch_list_is_recorded_in_the_activity_feed(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'inputs' => [['channel' => 1, 'name' => 'KICK'], ['channel' => 2, 'name' => 'SNARE']],
            'outputs' => [['channel' => 1, 'name' => 'WEDGE']],
        ]);
        $this->assertResponseIsSuccessful();

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Rider, $item->id);

        $this->assertCount(1, $activities);
        $this->assertSame('rider_patch_list_updated', $activities[0]->type);
        $this->assertSame([
            'rider_name' => 'Rider',
            'title' => 'Patch list',
            'input_count' => 2,
            'output_count' => 1,
        ], $activities[0]->payload);
    }

    /**
     * Every save is recorded, unlike the text editor's autosave, which is coalesced because a
     * debounce would otherwise turn an afternoon of writing into forty feed entries. A grid is
     * saved deliberately, so a second entry a minute after the first is correct rather than noise.
     *
     * The earlier entry is seeded, again because loginUser() only survives one request.
     */
    public function test_a_save_soon_after_another_is_recorded_separately_rather_than_coalesced(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        BandSpaceActivityFactory::new([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Rider,
            'type' => 'rider_patch_list_updated',
            'resourceId' => Uuid::fromString((string) $item->id),
            'actor' => $user,
            'creationDatetime' => new DateTime('-1 minute'),
        ])->create();

        $this->put($user, $bandSpace, $rider, $item, ['inputs' => [['channel' => 2]], 'outputs' => []]);

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Rider, $item->id));
    }

    public function test_saving_a_patch_list_as_a_non_member_is_forbidden(): void
    {
        [, $bandSpace, $rider, $item] = $this->seed();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);

        $this->put($outsider, $bandSpace, $rider, $item, [
            'inputs' => [['channel' => 1, 'name' => 'KICK']],
            'outputs' => [],
        ]);

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

        $this->assertSame([], self::getContainer()->get(TechRiderPatchRowRepository::class)->findByItem($item));
    }

    public function test_saving_a_patch_list_is_blocked_when_the_space_is_pending_deletion(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new DateTimeImmutable('+30 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider'])->create();
        $item = TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::PatchList,
            'title' => 'Patch list',
            'position' => 0,
        ])->create();

        $this->put($user, $bandSpace, $rider, $item, [
            'inputs' => [['channel' => 1, 'name' => 'KICK']],
            'outputs' => [],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
        ]);

        $this->assertSame([], self::getContainer()->get(TechRiderPatchRowRepository::class)->findByItem($item));
    }

    public function test_saving_a_patch_list_on_an_archived_rider_is_blocked(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Rider',
            'archiveDatetime' => new DateTimeImmutable('-1 day'),
        ])->create();
        $item = TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::PatchList,
            'title' => 'Patch list',
            'position' => 0,
        ])->create();

        $this->put($user, $bandSpace, $rider, $item, [
            'inputs' => [['channel' => 1, 'name' => 'KICK']],
            'outputs' => [],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce tech rider est archivé, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Ce tech rider est archivé, les modifications sont désactivées',
        ]);

        $this->assertSame([], self::getContainer()->get(TechRiderPatchRowRepository::class)->findByItem($item));
    }

    /**
     * Deleting the item takes its rows with it. A CASCADE the application never exercises is a
     * CASCADE nobody notices is missing.
     */
    public function test_deleting_the_item_removes_its_rows(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $this->seedRows($item, [['input', 1, 'KICK'], ['output', 1, 'WEDGE']]);
        $itemId = (string) $item->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            $this->itemUrl($bandSpace, $rider, $item),
            [],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $repository = self::getContainer()->get(TechRiderPatchRowRepository::class);
        $this->assertSame([], $repository->createQueryBuilder('r')
            ->where('IDENTITY(r.item) = :itemId')
            ->setParameter('itemId', $itemId)
            ->getQuery()
            ->getResult());
    }

    /**
     * @return array{User, BandSpace, TechRider, TechRiderItem}
     */
    private function seed(): array
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider'])->create();
        $item = TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::PatchList,
            'title' => 'Patch list',
            'position' => 0,
        ])->create();

        return [$user, $bandSpace, $rider, $item];
    }

    /**
     * Seeds rows directly, so a test about a refused save is not resting on a successful one.
     *
     * @param list<array{0: string, 1: int, 2: string}> $rows direction, channel, name
     */
    private function seedRows(TechRiderItem $item, array $rows): void
    {
        $byDirection = [];
        foreach ($rows as [$direction, $channel, $name]) {
            $position = $byDirection[$direction] ?? 0;
            TechRiderPatchRowFactory::new([
                'item' => $item,
                'direction' => TechRiderPatchDirection::from($direction),
                'channel' => $channel,
                'name' => $name,
                'position' => $position,
            ])->create();
            $byDirection[$direction] = $position + 1;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function put(User $user, BandSpace $bandSpace, TechRider $rider, TechRiderItem $item, array $payload): void
    {
        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PUT',
            $this->itemUrl($bandSpace, $rider, $item) . '/patch_list',
            $payload,
            self::HEADERS,
        );
    }

    private function itemUrl(BandSpace $bandSpace, TechRider $rider, TechRiderItem $item): string
    {
        return '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id . '/items/' . $item->id;
    }
}
