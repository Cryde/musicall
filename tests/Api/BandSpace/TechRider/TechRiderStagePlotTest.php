<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderItem;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\TechRiderItemType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\TechRiderItemRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\BandSpace\TechRiderItemFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\BandSpace\TechRider\TechRiderStagePlot;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A stage plot is raw JSON from a canvas editor, so most of this file is the boundary: every
 * number is something a dragging bug could put out of range, and the document is read back by an
 * export that cannot ask questions about it.
 */
#[ResetDatabase]
class TechRiderStagePlotTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_saving_a_plot_stores_it_and_returns_it_on_the_item(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $plot = [
            'version' => 1,
            'stage' => ['aspect_ratio' => 1.4],
            'elements' => [
                [
                    'id' => 'el-1',
                    'icon' => 'drum_kit',
                    'x' => 0.42,
                    'y' => 0.55,
                    'scale' => 1.0,
                    'rotation' => 0,
                    'label' => 'Batterie',
                    'colour' => null,
                ],
                [
                    'id' => 'el-2',
                    'icon' => 'wedge_monitor',
                    'x' => 0.1,
                    'y' => 0.9,
                    'scale' => 0.75,
                    'rotation' => 270,
                    'label' => 'Retour côté jardin',
                    'colour' => 'cyan',
                ],
            ],
            'legend' => [['icon' => 'wedge_monitor', 'label' => 'Retours']],
        ];

        $this->put($user, $bandSpace, $rider, $item, ['plot' => $plot]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderItem',
            '@id' => $this->itemUrl($bandSpace, $rider, $item),
            '@type' => 'TechRiderItem',
            'id' => (string) $item->id,
            'band_space_id' => (string) $bandSpace->id,
            'rider_id' => (string) $rider->id,
            'type' => 'stage_plot',
            'is_included' => true,
            'title' => 'Plan de scène',
            // The plot comes back as the item's content, byte for byte what was sent.
            'content' => $plot,
            'file' => null,
            'patch_list' => null,
            'contacts' => null,
            'position' => 0,
            'creation_datetime' => $item->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $item->updateDatetime?->format(\DateTimeInterface::ATOM),
        ]);

        $stored = self::getContainer()->get(TechRiderItemRepository::class)
            ->findOneByIdAndRider((string) $item->id, $rider);
        $this->assertSame($plot, $stored?->content);
    }

    /** Clearing the plot without deleting the item. */
    public function test_a_null_plot_clears_it(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $item->content = ['version' => 1, 'elements' => []];
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->put($user, $bandSpace, $rider, $item, ['plot' => null]);

        $this->assertResponseIsSuccessful();
        $this->assertNull(
            self::getContainer()->get(TechRiderItemRepository::class)
                ->findOneByIdAndRider((string) $item->id, $rider)?->content,
        );
    }

    public function test_an_empty_element_list_is_accepted(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [], 'legend' => []],
        ]);

        $this->assertResponseIsSuccessful();
    }

    /**
     * Coordinates are fractions of the stage box, so anything outside 0..1 is off the stage. Both
     * ends are covered because a clamp bug usually breaks only one.
     */
    #[DataProvider('outOfRangeCoordinates')]
    public function test_a_coordinate_outside_the_stage_is_refused(string $axis, float $value): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $element = ['id' => 'el-1', 'icon' => 'drum_kit', 'x' => 0.5, 'y' => 0.5];
        $element[$axis] = $value;

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [$element]],
        ]);

        $this->assertViolation(
            "plot.elements[0].$axis",
            'La position doit être comprise entre 0 et 1',
        );
    }

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function outOfRangeCoordinates(): iterable
    {
        yield 'x just below the stage' => ['x', -0.01];
        yield 'x just past the stage' => ['x', 1.01];
        yield 'y just below the stage' => ['y', -0.01];
        yield 'y just past the stage' => ['y', 1.01];
    }

    public function test_a_scale_outside_the_range_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [
                ['id' => 'el-1', 'icon' => 'drum_kit', 'x' => 0.5, 'y' => 0.5, 'scale' => 8.0],
            ]],
        ]);

        $this->assertViolation('plot.elements[0].scale', 'La taille doit être comprise entre 0.25 et 4');
    }

    /**
     * The inverse of what this used to assert. Rotation was capped at quarter turns while the export
     * engine was undecided, because arbitrary angles were not renderable everywhere; #741 settled on
     * Chromium, which turns by any angle, so 45 is now a perfectly ordinary value.
     */
    public function test_an_arbitrary_angle_is_accepted(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [
                ['id' => 'el-1', 'icon' => 'drum_kit', 'x' => 0.5, 'y' => 0.5, 'rotation' => 47],
            ]],
        ]);

        $this->assertResponseIsSuccessful();
    }

    /**
     * Stored verbatim, not rounded to anything. An editor that snaps to 15 degrees is a convenience;
     * the document has to keep whatever angle it was given, or a plot drawn with Alt held comes back
     * subtly different from the one that was saved.
     */
    public function test_an_arbitrary_angle_survives_the_round_trip_unrounded(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $plot = ['version' => 1, 'elements' => [
            ['id' => 'el-1', 'icon' => 'drum_kit', 'x' => 0.5, 'y' => 0.5, 'rotation' => 47],
        ]];

        $this->put($user, $bandSpace, $rider, $item, ['plot' => $plot]);
        $this->assertResponseIsSuccessful();

        $stored = self::getContainer()->get(TechRiderItemRepository::class)->find((string) $item->id);
        $this->assertSame($plot, $stored?->content);
    }

    /**
     * Both ends of the range, and 359 is the one that matters: it is the only value that catches the
     * upper bound being written as >= rather than >. Every other accepted angle sits comfortably
     * inside, so without this the off-by-one passes.
     */
    #[DataProvider('acceptedRotationProvider')]
    public function test_an_accepted_rotation_is_stored(int $rotation): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [
                ['id' => 'el-1', 'icon' => 'drum_kit', 'x' => 0.5, 'y' => 0.5, 'rotation' => $rotation],
            ]],
        ]);

        $this->assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function acceptedRotationProvider(): iterable
    {
        yield 'the lower bound' => [TechRiderStagePlot::MIN_ROTATION];
        yield 'the upper bound' => [TechRiderStagePlot::MAX_ROTATION];
        yield 'a quarter turn' => [270];
    }

    /**
     * What is actually out of bounds now. 360 and a negative are refused so an angle has exactly one
     * representation, and a float is refused so 90 and 90.0 cannot both mean the same rotation.
     *
     * One case per test rather than one payload with three bad elements, because assertViolation
     * asserts the whole response body and therefore exactly one violation.
     */
    #[DataProvider('invalidRotationProvider')]
    public function test_an_out_of_range_rotation_is_refused(int|float|string $rotation): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [
                ['id' => 'el-1', 'icon' => 'drum_kit', 'x' => 0.5, 'y' => 0.5, 'rotation' => $rotation],
            ]],
        ]);

        $this->assertViolation('plot.elements[0].rotation', 'La rotation doit être un entier de 0 à 359 degrés');
    }

    /**
     * @return iterable<string, array{int|float|string}>
     */
    public static function invalidRotationProvider(): iterable
    {
        yield 'a full turn, which is the same angle as zero' => [360];
        yield 'a negative angle' => [-1];
        // This one only reaches the server as a float because jsonRequest() encodes with
        // JSON_PRESERVE_ZERO_FRACTION; a plain json_encode(90.0) emits 90 and decodes back to an int.
        // So it guards against a hand-written or non-JavaScript client, not against our own frontend,
        // which cannot produce a trailing .0 through JSON.stringify at all.
        yield 'a whole angle written as a float' => [90.0];
        yield 'a numeric string' => ['90'];
    }

    public function test_an_unknown_icon_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [
                ['id' => 'el-1', 'icon' => 'theremin', 'x' => 0.5, 'y' => 0.5],
            ]],
        ]);

        $this->assertViolation('plot.elements[0].icon', 'Icône inconnue');
    }

    public function test_an_unknown_colour_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [
                ['id' => 'el-1', 'icon' => 'drum_kit', 'x' => 0.5, 'y' => 0.5, 'colour' => 'chartreuse'],
            ]],
        ]);

        $this->assertViolation('plot.elements[0].colour', 'Couleur inconnue');
    }

    /**
     * A client sending `posX` would otherwise get a success and a plot that quietly lost its
     * positions.
     */
    public function test_an_unknown_key_in_an_element_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [
                ['id' => 'el-1', 'icon' => 'drum_kit', 'x' => 0.5, 'y' => 0.5, 'posX' => 12],
            ]],
        ]);

        $this->assertViolation('plot.elements[0]', 'Champ inconnu dans le plan de scène');
    }

    public function test_an_unknown_top_level_key_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [], 'background' => 'stage.png'],
        ]);

        $this->assertViolation('plot', 'Champ inconnu dans le plan de scène');
    }

    /** A document with no version is one whose shape nobody can vouch for. */
    public function test_a_wrong_or_missing_version_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, ['plot' => ['elements' => []]]);

        $this->assertViolation('plot.version', 'Version de plan de scène non prise en charge');
    }

    public function test_more_elements_than_the_cap_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $elements = [];
        for ($index = 0; $index <= TechRiderStagePlot::MAX_ELEMENTS; $index++) {
            $elements[] = ['id' => 'el-' . $index, 'icon' => 'drum_kit', 'x' => 0.5, 'y' => 0.5];
        }

        $this->put($user, $bandSpace, $rider, $item, ['plot' => ['version' => 1, 'elements' => $elements]]);

        $this->assertViolation('plot.elements', 'Un plan de scène ne peut pas dépasser 120 éléments');
    }

    public function test_exactly_the_element_cap_is_accepted(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $elements = [];
        for ($index = 0; $index < TechRiderStagePlot::MAX_ELEMENTS; $index++) {
            $elements[] = ['id' => 'el-' . $index, 'icon' => 'drum_kit', 'x' => 0.5, 'y' => 0.5];
        }

        $this->put($user, $bandSpace, $rider, $item, ['plot' => ['version' => 1, 'elements' => $elements]]);

        $this->assertResponseIsSuccessful();
    }

    public function test_more_legend_entries_than_the_cap_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $legend = [];
        for ($index = 0; $index <= TechRiderStagePlot::MAX_LEGEND_ENTRIES; $index++) {
            $legend[] = ['icon' => 'drum_kit', 'label' => 'Entrée ' . $index];
        }

        $this->put($user, $bandSpace, $rider, $item, ['plot' => ['version' => 1, 'legend' => $legend]]);

        $this->assertViolation('plot.legend', 'La légende ne peut pas dépasser 20 entrées');
    }

    public function test_an_over_length_label_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [[
                'id' => 'el-1',
                'icon' => 'drum_kit',
                'x' => 0.5,
                'y' => 0.5,
                'label' => str_repeat('é', TechRiderStagePlot::MAX_LABEL_LENGTH + 1),
            ]]],
        ]);

        $this->assertViolation('plot.elements[0].label', 'Le libellé ne peut pas dépasser 60 caractères');
    }

    public function test_an_aspect_ratio_outside_the_range_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'stage' => ['aspect_ratio' => 9.0], 'elements' => []],
        ]);

        $this->assertViolation('plot.stage.aspect_ratio', 'Le format de scène doit être compris entre 0.5 et 3');
    }

    /** A refused save must leave the stored plot alone, as the patch list one does. */
    public function test_a_refused_save_leaves_the_stored_plot_intact(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $existing = ['version' => 1, 'elements' => [
            ['id' => 'keep', 'icon' => 'drum_kit', 'x' => 0.2, 'y' => 0.3],
        ]];
        $item->content = $existing;
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [
                ['id' => 'el-1', 'icon' => 'drum_kit', 'x' => 5.0, 'y' => 0.5],
            ]],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame(
            $existing,
            self::getContainer()->get(TechRiderItemRepository::class)
                ->findOneByIdAndRider((string) $item->id, $rider)?->content,
        );
    }

    /**
     * The hole this endpoint's validator would otherwise have. The generic item endpoints only
     * check the content column's size and depth, so if they accepted a stage plot's content they
     * would be a way to store an unknown icon and off-stage coordinates with every structural rule
     * bypassed. The strict validator has to be the only door in.
     */
    public function test_the_generic_create_endpoint_refuses_stage_plot_content(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id . '/items',
            [
                'title' => 'Contournement',
                'type' => 'stage_plot',
                // Every rule in the plot validator broken at once.
                'content' => [
                    'version' => 999,
                    'unknown_top_level_key' => 'x',
                    'elements' => [
                        ['id' => 'x', 'icon' => 'pas_une_icone', 'x' => 50.0, 'y' => -30.0, 'rotation' => 360],
                    ],
                ],
            ],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Un plan de scène se modifie via son endpoint dédié',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Un plan de scène se modifie via son endpoint dédié',
        ]);
    }

    public function test_the_generic_patch_endpoint_refuses_stage_plot_content(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->itemUrl($bandSpace, $rider, $item),
            ['content' => ['version' => 999, 'elements' => [['id' => 'x', 'icon' => 'pas_une_icone', 'x' => 9.0, 'y' => 9.0]]]],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Un plan de scène se modifie via son endpoint dédié',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Un plan de scène se modifie via son endpoint dédié',
        ]);

        $this->assertNull(
            self::getContainer()->get(TechRiderItemRepository::class)
                ->findOneByIdAndRider((string) $item->id, $rider)?->content,
        );
    }

    /**
     * The plot carries no byte ceiling of its own, unlike a generic content write, so the strings
     * inside it are what has to be bounded.
     */
    public function test_an_over_length_element_id_is_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [[
                'id' => str_repeat('a', TechRiderStagePlot::MAX_ELEMENT_ID_LENGTH + 1),
                'icon' => 'drum_kit',
                'x' => 0.5,
                'y' => 0.5,
            ]]],
        ]);

        $this->assertViolation(
            'plot.elements[0].id',
            "L'identifiant ne peut pas dépasser 64 caractères",
        );
    }

    /** An editor keying its canvas by id would silently drop one of a duplicated pair. */
    public function test_two_elements_sharing_an_id_are_refused(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [
                ['id' => 'same', 'icon' => 'drum_kit', 'x' => 0.1, 'y' => 0.1],
                ['id' => 'same', 'icon' => 'guitar_amp', 'x' => 0.2, 'y' => 0.2],
            ]],
        ]);

        $this->assertViolation('plot.elements', 'Chaque élément doit avoir un identifiant unique');
    }

    public function test_saving_a_plot_on_another_item_type_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider'])->create();
        $textItem = TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::Text,
            'title' => 'Sonorisation',
            'position' => 0,
        ])->create();

        $this->put($user, $bandSpace, $rider, $textItem, ['plot' => ['version' => 1, 'elements' => []]]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Seul un élément de type plan de scène peut contenir un plan de scène',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Seul un élément de type plan de scène peut contenir un plan de scène',
        ]);
    }

    public function test_saving_onto_an_item_of_another_rider_is_not_found(): void
    {
        [$user, $bandSpace, $rider] = $this->seed();
        $otherRider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $theirItem = TechRiderItemFactory::new([
            'techRider' => $otherRider,
            'type' => TechRiderItemType::StagePlot,
            'title' => 'Ailleurs',
            'position' => 0,
        ])->create();

        $this->put($user, $bandSpace, $rider, $theirItem, ['plot' => ['version' => 1, 'elements' => []]]);

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
    }

    public function test_saving_a_plot_is_recorded_in_the_activity_feed(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();

        $this->put($user, $bandSpace, $rider, $item, [
            'plot' => ['version' => 1, 'elements' => [
                ['id' => 'el-1', 'icon' => 'drum_kit', 'x' => 0.5, 'y' => 0.5],
                ['id' => 'el-2', 'icon' => 'guitar_amp', 'x' => 0.2, 'y' => 0.4],
            ]],
        ]);
        $this->assertResponseIsSuccessful();

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Rider, $item->id);

        $this->assertCount(1, $activities);
        $this->assertSame('rider_stage_plot_updated', $activities[0]->type);
        $this->assertSame([
            'rider_name' => 'Rider',
            'title' => 'Plan de scène',
            'element_count' => 2,
        ], $activities[0]->payload);
    }

    public function test_saving_a_plot_as_a_non_member_is_forbidden(): void
    {
        [, $bandSpace, $rider, $item] = $this->seed();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);

        $this->put($outsider, $bandSpace, $rider, $item, ['plot' => ['version' => 1, 'elements' => []]]);

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

    public function test_saving_a_plot_is_blocked_when_the_space_is_pending_deletion(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new DateTimeImmutable('+30 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider'])->create();
        $item = TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::StagePlot,
            'title' => 'Plan de scène',
            'position' => 0,
        ])->create();

        $this->put($user, $bandSpace, $rider, $item, ['plot' => ['version' => 1, 'elements' => []]]);

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
    }

    public function test_saving_a_plot_on_an_archived_rider_is_blocked(): void
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
            'type' => TechRiderItemType::StagePlot,
            'title' => 'Plan de scène',
            'position' => 0,
        ])->create();

        $this->put($user, $bandSpace, $rider, $item, ['plot' => ['version' => 1, 'elements' => []]]);

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
    }

    private function assertViolation(string $propertyPath, string $message): void
    {
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderStagePlot::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => $propertyPath, 'message' => $message, 'code' => TechRiderStagePlot::ERROR_CODE],
            ],
            'detail' => $propertyPath . ': ' . $message,
            'type' => '/validation_errors/' . TechRiderStagePlot::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => $propertyPath . ': ' . $message,
        ]);
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
            'type' => TechRiderItemType::StagePlot,
            'title' => 'Plan de scène',
            'position' => 0,
        ])->create();

        return [$user, $bandSpace, $rider, $item];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function put(User $user, BandSpace $bandSpace, TechRider $rider, TechRiderItem $item, array $payload): void
    {
        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PUT',
            $this->itemUrl($bandSpace, $rider, $item) . '/stage_plot',
            $payload,
            self::HEADERS,
        );
    }

    private function itemUrl(BandSpace $bandSpace, TechRider $rider, TechRiderItem $item): string
    {
        return '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id . '/items/' . $item->id;
    }
}
