<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\TechRider;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\TechRiderItemType;
use App\Enum\BandSpace\TechRiderPatchDirection;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\TechRiderItemRepository;
use App\Repository\BandSpace\TechRiderPatchRowRepository;
use App\Repository\BandSpace\TechRiderRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\BandSpace\TechRiderItemFactory;
use App\Tests\Factory\BandSpace\TechRiderPatchRowFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Duplicating is how next year's rider gets made, so the property that matters is independence: a
 * shallow copy sharing rows would not be a missing feature, it would be data corruption, and the
 * band would not notice until they opened last year's archived document and found it rewritten.
 *
 * A naive equality test passes on a shallow clone, which is why the central test here edits the
 * copy and reads the original back.
 */
#[ResetDatabase]
class TechRiderDuplicateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    private const array SAMPLE_DOC = [
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Deux retours']]]],
    ];

    /**
     * The test this file exists for. Every mutable part of the copy is changed, then the original is
     * read back and asserted unchanged.
     */
    public function test_editing_the_copy_leaves_the_original_untouched(): void
    {
        [$user, $bandSpace, $source] = $this->seedPopulatedRider();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', $this->duplicateUrl($bandSpace, $source), [], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $copyId = $this->getResponseAsArray()['id'];
        $entityManager = self::getContainer()->get('doctrine')->getManager();

        $copy = self::getContainer()->get(TechRiderRepository::class)->findOneByIdAndBandSpace($copyId, $bandSpace);
        self::assertInstanceOf(TechRider::class, $copy);

        // Rewrite everything mutable on the copy.
        $copy->name = 'Renommé';
        foreach ($copy->items as $item) {
            $item->title = 'Titre modifié';
            $item->isIncluded = false;
            if ($item->content !== null) {
                $item->content = ['type' => 'doc', 'content' => []];
            }
            $item->file = null;
            foreach ($item->patchRows as $row) {
                $row->name = 'MODIFIÉ';
                $row->channel = 99;
            }
        }
        $entityManager->flush();

        $sourceId = (string) $source->id;
        $bandSpaceId = (string) $bandSpace->id;
        // Cleared so the reads below come from the database rather than from the identity map,
        // where a shared reference would still look correct. Everything held before this point is
        // detached afterwards, so the band space is re-loaded rather than reused.
        $entityManager->clear();

        $freshBandSpace = $entityManager->find(BandSpace::class, $bandSpaceId);
        self::assertInstanceOf(BandSpace::class, $freshBandSpace);
        $reloaded = self::getContainer()->get(TechRiderRepository::class)
            ->findOneByIdAndBandSpace($sourceId, $freshBandSpace);
        self::assertInstanceOf(TechRider::class, $reloaded);

        $this->assertSame('Rider 2026', $reloaded->name);
        $items = $reloaded->items->toArray();
        usort($items, static fn ($a, $b): int => $a->position <=> $b->position);

        $this->assertSame(
            ['Sonorisation', 'Patch', 'Schéma', 'Membres', 'Plan'],
            array_map(static fn ($item): string => $item->title, $items),
        );
        $this->assertTrue($items[0]->isIncluded);
        $this->assertSame(self::SAMPLE_DOC, $items[0]->content);
        $this->assertNotNull($items[2]->file, 'The document item kept its file reference.');

        $patchRows = $items[1]->patchRows->toArray();
        usort(
            $patchRows,
            static fn ($a, $b): int => [$a->direction->value, $a->position] <=> [$b->direction->value, $b->position],
        );
        $this->assertSame(
            [['input', 1, 'KICK IN'], ['input', 2, 'SNARE'], ['output', 1, 'WEDGE']],
            array_map(
                static fn ($row): array => [$row->direction->value, $row->channel, $row->name],
                $patchRows,
            ),
        );
    }

    /**
     * The whole response once. Every other success test here reads a slice, so without this a
     * regression in the envelope, a leaked serialization group or a snake_case drift on this
     * endpoint specifically would go unnoticed.
     */
    public function test_the_full_response_is_the_new_rider(): void
    {
        [$user, $bandSpace, $source] = $this->seedPopulatedRider();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->duplicateUrl($bandSpace, $source),
            ['name' => 'Rider 2027'],
            self::HEADERS,
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = $this->getResponseAsArray();
        $copyId = $body['id'];
        $itemIds = array_column($body['items'], 'id');
        $patchRows = $body['items'][1]['patch_list'];
        $fileId = (string) $this->itemOfType($source, TechRiderItemType::Document)->file?->id;

        $itemUrl = static fn (string $itemId): string =>
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $copyId . '/items/' . $itemId;
        $itemBase = static fn (int $index, string $type, string $title, int $position): array => [
            '@id' => $itemUrl($itemIds[$index]),
            '@type' => 'TechRiderItem',
            'id' => $itemIds[$index],
            'band_space_id' => (string) $bandSpace->id,
            'rider_id' => $copyId,
            'type' => $type,
            'is_included' => true,
            'title' => $title,
            'content' => null,
            'file' => null,
            'patch_list' => null,
            'contacts' => null,
            'position' => $position,
            'creation_datetime' => $body['items'][$index]['creation_datetime'],
            'update_datetime' => null,
        ];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRider',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $copyId,
            '@type' => 'TechRider',
            'id' => $copyId,
            'band_space_id' => (string) $bandSpace->id,
            'name' => 'Rider 2027',
            'created_by_username' => $user->username,
            'archive_datetime' => null,
            'creation_datetime' => $body['creation_datetime'],
            'update_datetime' => null,
            'items' => [
                [...$itemBase(0, 'text', 'Sonorisation', 0), 'content' => self::SAMPLE_DOC],
                [...$itemBase(1, 'patch_list', 'Patch', 1), 'patch_list' => $patchRows],
                [
                    ...$itemBase(2, 'document', 'Schéma', 2),
                    'file' => [
                        'id' => $fileId,
                        'original_name' => 'schema.png',
                        'mime_type' => 'image/png',
                        'is_archived' => false,
                        'download_url' => '/api/band_spaces/' . $bandSpace->id . '/files/' . $fileId . '/download',
                    ],
                ],
                [
                    ...$itemBase(3, 'contacts', 'Membres', 3),
                    'content' => ['showEmails' => true, 'note' => self::SAMPLE_DOC],
                    // Rendered live from the roster, so the copy is correct the moment it exists.
                    'contacts' => [
                        'show_emails' => true,
                        'lines' => [$user->username],
                        'emails' => [$user->email],
                    ],
                ],
                [
                    ...$itemBase(4, 'stage_plot', 'Plan', 4),
                    'content' => $body['items'][4]['content'],
                ],
            ],
            'item_count' => 5,
        ]);

        // The parts echoed from the response above, pinned here so the assertion is not circular.
        $this->assertSame(
            ['KICK IN', 'SNARE'],
            array_column($patchRows['inputs'], 'name'),
        );
        $this->assertSame(['WEDGE'], array_column($patchRows['outputs'], 'name'));
        $this->assertSame(1, $body['items'][4]['content']['version']);
        $this->assertCount(1, $body['items'][4]['content']['elements']);
    }

    /** Every id in the copy differs, at every level. */
    public function test_the_copy_shares_no_ids_with_the_original(): void
    {
        [$user, $bandSpace, $source] = $this->seedPopulatedRider();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', $this->duplicateUrl($bandSpace, $source), [], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $copyId = $this->getResponseAsArray()['id'];
        $this->assertNotSame((string) $source->id, $copyId);

        $itemRepository = self::getContainer()->get(TechRiderItemRepository::class);
        $rowRepository = self::getContainer()->get(TechRiderPatchRowRepository::class);
        $copy = self::getContainer()->get(TechRiderRepository::class)->findOneByIdAndBandSpace($copyId, $bandSpace);

        $sourceItemIds = array_map(
            static fn ($item): string => (string) $item->id,
            $itemRepository->findByRider($source),
        );
        $copyItemIds = array_map(
            static fn ($item): string => (string) $item->id,
            $itemRepository->findByRider($copy),
        );

        $this->assertCount(5, $copyItemIds);
        $this->assertSame([], array_intersect($sourceItemIds, $copyItemIds));

        $patchItem = array_values(array_filter(
            $itemRepository->findByRider($copy),
            static fn ($item): bool => $item->type === TechRiderItemType::PatchList,
        ));
        $copyRowIds = array_map(
            static fn ($row): string => (string) $row->id,
            $rowRepository->findByItem($patchItem[0]),
        );
        $this->assertCount(3, $copyRowIds);
    }

    /**
     * A document item points at a file the band already has. Duplicating a rider must not duplicate
     * a 40MB PDF, touch the quota, or leave two copies of a diagram to keep current.
     */
    public function test_a_document_item_shares_the_file_rather_than_copying_it(): void
    {
        [$user, $bandSpace, $source] = $this->seedPopulatedRider();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', $this->duplicateUrl($bandSpace, $source), [], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = $this->getResponseAsArray();
        $sourceFileId = (string) $this->itemOfType($source, TechRiderItemType::Document)->file?->id;
        $copyDocument = array_values(array_filter(
            $body['items'],
            static fn (array $item): bool => $item['type'] === 'document',
        ));

        $this->assertSame($sourceFileId, $copyDocument[0]['file']['id']);
    }

    public function test_positions_are_preserved(): void
    {
        [$user, $bandSpace, $source] = $this->seedPopulatedRider();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', $this->duplicateUrl($bandSpace, $source), [], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = $this->getResponseAsArray();
        $this->assertSame([0, 1, 2, 3, 4], array_column($body['items'], 'position'));
        $this->assertSame(
            ['Sonorisation', 'Patch', 'Schéma', 'Membres', 'Plan'],
            array_column($body['items'], 'title'),
        );

        $patch = array_values(array_filter(
            $body['items'],
            static fn (array $item): bool => $item['type'] === 'patch_list',
        ));
        $this->assertSame([0, 1], array_column($patch[0]['patch_list']['inputs'], 'position'));
        $this->assertSame([0], array_column($patch[0]['patch_list']['outputs'], 'position'));
    }

    public function test_the_default_name_suffixes_the_source(): void
    {
        [$user, $bandSpace, $source] = $this->seedPopulatedRider();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', $this->duplicateUrl($bandSpace, $source), [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('Rider 2026 (copie)', $this->getResponseAsArray()['name']);
    }

    /**
     * The source name is trimmed rather than the whole result, so the suffix survives: it is the
     * part that tells a reader which of two identically named riders is the new one.
     */
    public function test_a_maximum_length_name_does_not_overflow_the_column(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $source = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => str_repeat('é', 255)])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', $this->duplicateUrl($bandSpace, $source), [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $name = $this->getResponseAsArray()['name'];
        $this->assertSame(255, mb_strlen($name));
        $this->assertStringEndsWith(' (copie)', $name);
    }

    public function test_an_explicit_name_is_used(): void
    {
        [$user, $bandSpace, $source] = $this->seedPopulatedRider();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->duplicateUrl($bandSpace, $source),
            ['name' => 'Tech rider 2027'],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('Tech rider 2027', $this->getResponseAsArray()['name']);
    }

    public function test_a_blank_name_is_refused(): void
    {
        [$user, $bandSpace, $source] = $this->seedPopulatedRider();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->duplicateUrl($bandSpace, $source),
            ['name' => '   '],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'name',
                    'message' => 'Veuillez spécifier un nom',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'name: Veuillez spécifier un nom',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'name: Veuillez spécifier un nom',
        ]);
    }

    public function test_the_duplicating_user_becomes_the_author(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'author_user']);
        $duplicator = UserFactory::new()->create(['username' => 'duplicator', 'email' => 'dup@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $duplicator])->create();
        $source = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Rider 2026',
            'createdBy' => $author,
        ])->create();

        $this->client->loginUser($duplicator);
        $this->client->jsonRequest('POST', $this->duplicateUrl($bandSpace, $source), [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('duplicator', $this->getResponseAsArray()['created_by_username']);
    }

    /**
     * The main reason unarchive and duplicate both exist: last year's rider is archived and this
     * year's starts from it, so the copy has to come back live.
     */
    public function test_duplicating_an_archived_rider_produces_a_live_one(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $source = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Rider 2026',
            'archiveDatetime' => new DateTimeImmutable('-1 year'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', $this->duplicateUrl($bandSpace, $source), [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertNull($this->getResponseAsArray()['archive_datetime']);
    }

    public function test_the_duplication_is_recorded_in_the_activity_feed(): void
    {
        [$user, $bandSpace, $source] = $this->seedPopulatedRider();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', $this->duplicateUrl($bandSpace, $source), [], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $copyId = $this->getResponseAsArray()['id'];
        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Rider, $copyId);

        $this->assertCount(1, $activities);
        $this->assertSame('rider_duplicated', $activities[0]->type);
        $this->assertSame([
            'name' => 'Rider 2026 (copie)',
            'source_name' => 'Rider 2026',
            'source_id' => (string) $source->id,
        ], $activities[0]->payload);
    }

    public function test_duplicating_as_a_non_member_is_forbidden(): void
    {
        [, $bandSpace, $source] = $this->seedPopulatedRider();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);

        $this->client->loginUser($outsider);
        $this->client->jsonRequest('POST', $this->duplicateUrl($bandSpace, $source), [], self::HEADERS);

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

    public function test_duplicating_a_rider_of_another_space_is_not_found(): void
    {
        [$user, $bandSpace] = $this->seedPopulatedRider();
        $elsewhere = TechRiderFactory::new(['bandSpace' => BandSpaceFactory::new()->create()])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', $this->duplicateUrl($bandSpace, $elsewhere), [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Tech rider introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Tech rider introuvable',
        ]);
    }

    public function test_duplicating_is_blocked_when_the_space_is_pending_deletion(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new DateTimeImmutable('+30 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $source = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider 2026'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', $this->duplicateUrl($bandSpace, $source), [], self::HEADERS);

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

    private function itemOfType(TechRider $rider, TechRiderItemType $type): object
    {
        $items = array_values(array_filter(
            self::getContainer()->get(TechRiderItemRepository::class)->findByRider($rider),
            static fn ($item): bool => $item->type === $type,
        ));
        self::assertNotEmpty($items, sprintf('No %s item was seeded.', $type->value));

        return $items[0];
    }

    /**
     * One rider holding an item of every type, so a copy is exercised against the whole model
     * rather than against the two types that happen to be easiest to seed.
     *
     * @return array{User, BandSpace, TechRider}
     */
    private function seedPopulatedRider(): array
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $source = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Rider 2026',
            'createdBy' => $user,
        ])->create();

        TechRiderItemFactory::new([
            'techRider' => $source,
            'type' => TechRiderItemType::Text,
            'title' => 'Sonorisation',
            'content' => self::SAMPLE_DOC,
            'position' => 0,
        ])->create();

        $patchItem = TechRiderItemFactory::new([
            'techRider' => $source,
            'type' => TechRiderItemType::PatchList,
            'title' => 'Patch',
            'position' => 1,
        ])->create();
        TechRiderPatchRowFactory::new(['item' => $patchItem, 'direction' => TechRiderPatchDirection::Input, 'channel' => 1, 'name' => 'KICK IN', 'position' => 0])->create();
        TechRiderPatchRowFactory::new(['item' => $patchItem, 'direction' => TechRiderPatchDirection::Input, 'channel' => 2, 'name' => 'SNARE', 'position' => 1])->create();
        TechRiderPatchRowFactory::new(['item' => $patchItem, 'direction' => TechRiderPatchDirection::Output, 'channel' => 1, 'name' => 'WEDGE', 'position' => 0])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'originalName' => 'schema.png'])->create();
        $file->currentVersion = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file,
            'mimeType' => 'image/png',
            'versionNumber' => 1,
        ])->create();
        TechRiderItemFactory::new([
            'techRider' => $source,
            'type' => TechRiderItemType::Document,
            'title' => 'Schéma',
            'file' => $file,
            'position' => 2,
        ])->create();

        TechRiderItemFactory::new([
            'techRider' => $source,
            'type' => TechRiderItemType::Contacts,
            'title' => 'Membres',
            'content' => ['showEmails' => true, 'note' => self::SAMPLE_DOC],
            'position' => 3,
        ])->create();

        TechRiderItemFactory::new([
            'techRider' => $source,
            'type' => TechRiderItemType::StagePlot,
            'title' => 'Plan',
            'content' => [
                'version' => 1,
                'stage' => ['aspect_ratio' => 1.4],
                'elements' => [[
                    'id' => 'el-1',
                    'icon' => 'drum_kit',
                    'x' => 0.5,
                    'y' => 0.3,
                    'scale' => 1,
                    'rotation' => 0,
                    'label' => 'Batterie',
                    'colour' => null,
                ]],
                'legend' => [],
            ],
            'position' => 4,
        ])->create();

        self::getContainer()->get('doctrine')->getManager()->flush();

        return [$user, $bandSpace, $source];
    }

    private function duplicateUrl(BandSpace $bandSpace, TechRider $rider): string
    {
        return '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id . '/duplicate';
    }
}
