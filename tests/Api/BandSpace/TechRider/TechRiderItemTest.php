<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Entity\BandSpace\TechRiderItem;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\TechRiderItemRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\BandSpace\TechRiderItemFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\BandSpace\TechRider\TechRiderItemContent;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class TechRiderItemTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    private const array PATCH_HEADERS = [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    private const array SAMPLE_CONTENT = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Rack near the drums, 3m snakes.']],
            ],
        ],
    ];

    public function test_create_item(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider'])->create();
        TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Existant', 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->itemsUrl($bandSpace->id, $rider->id),
            ['title' => 'Sonorisation', 'content' => self::SAMPLE_CONTENT],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $items = self::getContainer()->get(TechRiderItemRepository::class)->findByRider($rider);
        $this->assertCount(2, $items);
        $created = $items[1];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderItem',
            '@id' => $this->itemsUrl($bandSpace->id, $rider->id) . '/' . $created->id,
            '@type' => 'TechRiderItem',
            'id' => (string) $created->id,
            'band_space_id' => (string) $bandSpace->id,
            'rider_id' => (string) $rider->id,
            'type' => 'text',
            'is_included' => true,
            'title' => 'Sonorisation',
            'content' => self::SAMPLE_CONTENT,
            // Appended after the existing section rather than inserted at the front.
            'position' => 1,
            'creation_datetime' => $created->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
            'file' => null,
        ]);

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Rider, $created->id);
        $this->assertCount(1, $activities);
        $this->assertSame('rider_item_added', $activities[0]->type);
        $this->assertSame(['rider_name' => 'Rider', 'title' => 'Sonorisation'], $activities[0]->payload);
    }

    public function test_create_item_title_required(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', $this->itemsUrl($bandSpace->id, $rider->id), ['title' => ''], self::HEADERS);

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
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'title: Veuillez spécifier un titre',
        ]);
    }

    public function test_create_item_content_too_large(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $oversized = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => str_repeat('a', TechRiderItemContent::MAX_CONTENT_BYTES)]],
            ]],
        ];

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->itemsUrl($bandSpace->id, $rider->id),
            ['title' => 'Trop long', 'content' => $oversized],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderItemContent::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Le contenu de l\'élément est trop volumineux (limite : 200 Ko)',
                    'code' => TechRiderItemContent::ERROR_CODE,
                ],
            ],
            'detail' => 'content: Le contenu de l\'élément est trop volumineux (limite : 200 Ko)',
            'type' => '/validation_errors/' . TechRiderItemContent::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'content: Le contenu de l\'élément est trop volumineux (limite : 200 Ko)',
        ]);
    }

    /**
     * The cap has to hold on PATCH too, which is the path the autosaving editor actually
     * uses. It runs against the merged resource rather than a bare input DTO, so it is a
     * different route through the same constraint.
     */
    public function test_patch_content_too_large_is_rejected_and_changes_nothing(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $item = TechRiderItemFactory::new([
            'techRider' => $rider,
            'title' => 'Sonorisation',
            'content' => self::SAMPLE_CONTENT,
        ])->create();

        $oversized = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => str_repeat('a', TechRiderItemContent::MAX_CONTENT_BYTES)]],
            ]],
        ];

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->itemsUrl($bandSpace->id, $rider->id) . '/' . $item->id,
            ['content' => $oversized],
            self::PATCH_HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderItemContent::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Le contenu de l\'élément est trop volumineux (limite : 200 Ko)',
                    'code' => TechRiderItemContent::ERROR_CODE,
                ],
            ],
            'detail' => 'content: Le contenu de l\'élément est trop volumineux (limite : 200 Ko)',
            'type' => '/validation_errors/' . TechRiderItemContent::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'content: Le contenu de l\'élément est trop volumineux (limite : 200 Ko)',
        ]);

        $stored = self::getContainer()->get(TechRiderItemRepository::class)
            ->findOneByIdAndRider((string) $item->id, $rider);
        $this->assertSame(self::SAMPLE_CONTENT, $stored?->content);
    }

    /**
     * The regression the raw-payload handling exists to prevent: a rename must not wipe what
     * the section already says.
     */
    public function test_patch_title_only_leaves_content_untouched(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider'])->create();
        $item = TechRiderItemFactory::new([
            'techRider' => $rider,
            'title' => 'Sonorisation',
            'content' => self::SAMPLE_CONTENT,
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->itemsUrl($bandSpace->id, $rider->id) . '/' . $item->id,
            ['title' => 'Sonorisation et retours'],
            self::PATCH_HEADERS,
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderItem',
            '@id' => $this->itemsUrl($bandSpace->id, $rider->id) . '/' . $item->id,
            '@type' => 'TechRiderItem',
            'id' => (string) $item->id,
            'band_space_id' => (string) $bandSpace->id,
            'rider_id' => (string) $rider->id,
            'type' => 'text',
            'is_included' => true,
            'title' => 'Sonorisation et retours',
            'content' => self::SAMPLE_CONTENT,
            'position' => 0,
            'creation_datetime' => $item->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => $item->updateDatetime?->format(DateTimeInterface::ATOM),
            'file' => null,
        ]);
    }

    public function test_patch_content_to_null_clears_it(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $item = TechRiderItemFactory::new([
            'techRider' => $rider,
            'title' => 'Divers',
            'content' => self::SAMPLE_CONTENT,
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->itemsUrl($bandSpace->id, $rider->id) . '/' . $item->id,
            ['content' => null],
            self::PATCH_HEADERS,
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderItem',
            '@id' => $this->itemsUrl($bandSpace->id, $rider->id) . '/' . $item->id,
            '@type' => 'TechRiderItem',
            'id' => (string) $item->id,
            'band_space_id' => (string) $bandSpace->id,
            'rider_id' => (string) $rider->id,
            'type' => 'text',
            'is_included' => true,
            'title' => 'Divers',
            'content' => null,
            'position' => 0,
            'creation_datetime' => $item->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => $item->updateDatetime?->format(DateTimeInterface::ATOM),
            'file' => null,
        ]);
    }

    public function test_delete_item(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider'])->create();
        $item = TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Catering', 'position' => 0])->create();
        $itemId = (string) $item->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', $this->itemsUrl($bandSpace->id, $rider->id) . '/' . $itemId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertCount(0, self::getContainer()->get(TechRiderItemRepository::class)->findByRider($rider));

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Rider, $itemId);
        $this->assertCount(1, $activities);
        $this->assertSame('rider_item_removed', $activities[0]->type);
        $this->assertSame(['rider_name' => 'Rider', 'title' => 'Catering'], $activities[0]->payload);
    }

    public function test_patch_item_from_another_rider_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $mine = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Mine'])->create();
        $other = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Other'])->create();
        $theirSection = TechRiderItemFactory::new(['techRider' => $other, 'title' => 'Ailleurs'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->itemsUrl($bandSpace->id, $mine->id) . '/' . $theirSection->id,
            ['title' => 'Détourné'],
            self::PATCH_HEADERS,
        );

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

    public function test_delete_item_from_another_rider_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $mine = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $other = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $theirSection = TechRiderItemFactory::new(['techRider' => $other])->create();

        $this->client->loginUser($user);
        $this->client->request('DELETE', $this->itemsUrl($bandSpace->id, $mine->id) . '/' . $theirSection->id);

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

    public function test_create_item_not_member(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($outsider);
        $this->client->jsonRequest(
            'POST',
            $this->itemsUrl($bandSpace->id, $rider->id),
            ['title' => 'Rejetée'],
            self::HEADERS,
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

    public function test_create_item_blocked_when_space_pending_deletion(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new DateTimeImmutable('+30 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->itemsUrl($bandSpace->id, $rider->id),
            ['title' => 'Bloquée'],
            self::HEADERS,
        );

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

    /**
     * Items are embedded on the rider and their order is the order of the composed document,
     * so it has to survive a round trip through the API rather than holding only in SQL.
     */
    public function test_items_come_back_inline_on_the_rider_in_position_order(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Rider',
            'creationDatetime' => new DateTime('2026-01-15T10:00:00+00:00'),
        ])->create();

        // Created out of order on purpose: position, not insertion order, decides.
        TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Troisième', 'position' => 2])->create();
        TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Première', 'position' => 0])->create();
        TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Deuxième', 'position' => 1])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id);

        $this->assertResponseIsSuccessful();
        $body = $this->getResponseAsArray();

        $this->assertSame(
            ['Première', 'Deuxième', 'Troisième'],
            array_column($body['items'], 'title'),
        );
        $this->assertSame(3, $body['item_count']);
    }

    /**
     * The collection is the rider switcher's payload. It must never carry item content:
     * riders gain patch rows and a stage plot next, and a space with several of them would
     * otherwise ship its whole rider corpus to render a dropdown of names.
     */
    public function test_collection_reports_item_count_and_never_item_content(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Rider',
            'creationDatetime' => new DateTime('2026-01-15T10:00:00+00:00'),
        ])->create();
        TechRiderItemFactory::new([
            'techRider' => $rider,
            'title' => 'Sonorisation',
            'content' => self::SAMPLE_CONTENT,
            'position' => 0,
        ])->create();
        TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Catering', 'position' => 1])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/tech_riders');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRider',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id,
                    '@type' => 'TechRider',
                    'id' => (string) $rider->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'name' => 'Rider',
                    'created_by_username' => null,
                    'archive_datetime' => null,
                    'creation_datetime' => $rider->creationDatetime->format(DateTimeInterface::ATOM),
                    'update_datetime' => null,
                    'item_count' => 2,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    /**
     * Excluding is not deleting. The item keeps its content and stays editable; it simply
     * drops out of the composed document, which is the whole point of the toggle.
     */
    public function test_excluding_an_item_keeps_it_and_its_content(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $item = TechRiderItemFactory::new([
            'techRider' => $rider,
            'title' => 'Plan B',
            'content' => self::SAMPLE_CONTENT,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->itemsUrl($bandSpace->id, $rider->id) . '/' . $item->id,
            ['is_included' => false],
            self::PATCH_HEADERS,
        );

        $this->assertResponseIsSuccessful();

        $stored = self::getContainer()->get(TechRiderItemRepository::class)
            ->findOneByIdAndRider((string) $item->id, $rider);
        $this->assertFalse($stored?->isIncluded);
        $this->assertSame(self::SAMPLE_CONTENT, $stored?->content);
        $this->assertSame('Plan B', $stored?->title);
    }

    /**
     * Composing the document is not editing it, so toggling an item in and out while deciding
     * what to send must not fill the activity feed.
     */
    public function test_toggling_inclusion_records_no_activity(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $item = TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Plan B'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->itemsUrl($bandSpace->id, $rider->id) . '/' . $item->id,
            ['is_included' => false],
            self::PATCH_HEADERS,
        );

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Rider, $item->id));
    }

    public function test_create_item_with_an_unknown_type_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->itemsUrl($bandSpace->id, $rider->id),
            ['title' => 'Bientôt', 'type' => 'stage_plot'],
            self::HEADERS,
        );

        // stage_plot arrives with #769. Until its renderer exists, offering the type would
        // create a block nothing can display.
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'type',
                    'message' => "Type d'élément inconnu",
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => "type: Type d'élément inconnu",
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
            'description' => "type: Type d'élément inconnu",
        ]);
    }

    /**
     * The interface renders an archived rider read only, but that is a curtain. A stale tab
     * or a direct call must not be able to edit a document the band has filed away.
     */
    public function test_items_of_an_archived_rider_cannot_be_created(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'archiveDatetime' => new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->itemsUrl($bandSpace->id, $rider->id),
            ['title' => 'Trop tard'],
            self::HEADERS,
        );

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

    public function test_items_of_an_archived_rider_cannot_be_edited(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'archiveDatetime' => new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
        ])->create();
        $item = TechRiderItemFactory::new([
            'techRider' => $rider,
            'title' => 'Gelé',
            'content' => self::SAMPLE_CONTENT,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->itemsUrl($bandSpace->id, $rider->id) . '/' . $item->id,
            ['title' => 'Modifié malgré tout'],
            self::PATCH_HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $stored = self::getContainer()->get(TechRiderItemRepository::class)
            ->findOneByIdAndRider((string) $item->id, $rider);
        $this->assertSame('Gelé', $stored?->title);
    }

    /**
     * Restoring is the way out of the archive, so it must never be caught by the guard that
     * protects archived riders.
     */
    public function test_an_archived_rider_can_still_be_restored(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'archiveDatetime' => new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id . '/unarchive',
            [],
            self::HEADERS,
        );

        $this->assertResponseIsSuccessful();
    }

    private function itemsUrl(string $bandSpaceId, string $riderId): string
    {
        return '/api/band_spaces/' . $bandSpaceId . '/tech_riders/' . $riderId . '/items';
    }
}
