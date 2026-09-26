<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\SetlistItem;
use App\Entity\User;
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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * « Partir d'une setlist existante » (#1062).
 */
#[ResetDatabase]
class SetlistItemsCopyTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_the_running_order_of_another_setlist_is_copied_with_its_details(): void
    {
        [$user, $space, $target] = $this->setlist('Festival d\'été');
        $song = SongFactory::new()->create(['bandSpace' => $space, 'title' => 'Neon Tide', 'tempo' => 132, 'tonality' => 'Bm', 'referenceDuration' => 232]);
        $source = SetlistFactory::new()->create(['bandSpace' => $space, 'name' => 'Tour 2026']);
        SetlistItemFactory::new(['setlist' => $source, 'type' => SetlistItemType::Song, 'song' => $song, 'label' => null, 'position' => 0, 'note' => 'départ à 4', 'transition' => 'enchaîné'])->create();
        SetlistItemFactory::new(['setlist' => $source, 'type' => SetlistItemType::Talk, 'label' => 'Présentation du groupe', 'durationOverride' => 90, 'position' => 1])->create();

        $this->client->loginUser($user);
        $this->copy($space, $target, ['from_setlist_id' => (string) $source->id]);

        $this->assertResponseIsSuccessful();
        $items = $this->items($target);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Setlist',
            '@id' => '/api/band_spaces/' . $space->id . '/setlists/' . $target->id,
            '@type' => 'Setlist',
            'id' => (string) $target->id,
            'band_space_id' => (string) $space->id,
            'name' => 'Festival d\'été',
            'target_duration' => null,
            'archive_datetime' => null,
            'creation_datetime' => $target->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $items[0]->setlist->updateDatetime->format(\DateTimeInterface::ATOM),
            'items' => [
                [
                    '@id' => '/api/band_spaces/' . $space->id . '/setlists/' . $target->id . '/items/' . $items[0]->id,
                    '@type' => 'SetlistItem',
                    'id' => (string) $items[0]->id,
                    'band_space_id' => (string) $space->id,
                    'setlist_id' => (string) $target->id,
                    'type' => 'song',
                    'song' => [
                        'id' => (string) $song->id,
                        'title' => 'Neon Tide',
                        'tempo' => 132,
                        'tonality' => 'Bm',
                        'reference_duration' => 232,
                        'has_lyrics' => false,
                        'archive_datetime' => null,
                        '@type' => 'SetlistItemSongInfo',
                    ],
                    'label' => null,
                    'duration_override' => null,
                    'note' => 'départ à 4',
                    'transition' => 'enchaîné',
                    'position' => 0,
                ],
                [
                    '@id' => '/api/band_spaces/' . $space->id . '/setlists/' . $target->id . '/items/' . $items[1]->id,
                    '@type' => 'SetlistItem',
                    'id' => (string) $items[1]->id,
                    'band_space_id' => (string) $space->id,
                    'setlist_id' => (string) $target->id,
                    'type' => 'talk',
                    'song' => null,
                    'label' => 'Présentation du groupe',
                    'duration_override' => 90,
                    'note' => null,
                    'transition' => null,
                    'position' => 1,
                ],
            ],
            'total_duration_seconds' => 322,
        ]);

        $this->assertCount(2, $this->items($source), 'The source keeps its own items');
        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($space, BandSpaceModule::Setlist, (string) $target->id);
        $this->assertCount(1, $activities);
        $this->assertSame('setlist_items_copied', $activities[0]->type);
        $this->assertSame(['count' => 2, 'name' => 'Festival d\'été', 'source_name' => 'Tour 2026'], $activities[0]->payload);
    }

    public function test_the_copy_goes_after_what_the_setlist_already_holds(): void
    {
        [$user, $space, $target] = $this->setlist('Festival d\'été');
        SetlistItemFactory::new(['setlist' => $target, 'type' => SetlistItemType::Talk, 'label' => 'Déjà là', 'position' => 0])->create();
        $source = SetlistFactory::new()->create(['bandSpace' => $space]);
        SetlistItemFactory::new(['setlist' => $source, 'type' => SetlistItemType::Break, 'label' => 'Pause', 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->copy($space, $target, ['from_setlist_id' => (string) $source->id]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            [['Déjà là', 0], ['Pause', 1]],
            array_map(static fn (SetlistItem $item): array => [$item->label, $item->position], $this->items($target)),
        );
    }

    public function test_an_archived_setlist_can_be_copied_from(): void
    {
        [$user, $space, $target] = $this->setlist('Festival d\'été');
        $source = SetlistFactory::new()->create(['bandSpace' => $space, 'archiveDatetime' => new \DateTimeImmutable('2026-09-01')]);
        SetlistItemFactory::new(['setlist' => $source, 'type' => SetlistItemType::Break, 'label' => 'Pause', 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->copy($space, $target, ['from_setlist_id' => (string) $source->id]);

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $this->items($target));
    }

    public function test_a_setlist_of_another_band_is_refused(): void
    {
        [$user, $space, $target] = $this->setlist('Festival d\'été');
        $theirs = SetlistFactory::new()->create(['bandSpace' => BandSpaceFactory::new()->create()]);

        $this->client->loginUser($user);
        $this->copy($space, $target, ['from_setlist_id' => (string) $theirs->id]);

        $this->assertUnprocessable('La setlist à copier n\'appartient pas à ce Band Space');
    }

    public function test_a_setlist_cannot_be_copied_into_itself(): void
    {
        [$user, $space, $target] = $this->setlist('Festival d\'été');

        $this->client->loginUser($user);
        $this->copy($space, $target, ['from_setlist_id' => (string) $target->id]);

        $this->assertUnprocessable('Choisissez une autre setlist que celle-ci');
    }

    public function test_an_empty_setlist_gives_nothing_to_copy(): void
    {
        [$user, $space, $target] = $this->setlist('Festival d\'été');
        $empty = SetlistFactory::new()->create(['bandSpace' => $space]);

        $this->client->loginUser($user);
        $this->copy($space, $target, ['from_setlist_id' => (string) $empty->id]);

        $this->assertUnprocessable('La setlist à copier est vide');
    }

    public function test_an_archived_setlist_takes_no_copy(): void
    {
        [$user, $space] = $this->setlist('Festival d\'été');
        $archived = SetlistFactory::new()->create(['bandSpace' => $space, 'archiveDatetime' => new \DateTimeImmutable('2026-09-01')]);
        $source = SetlistFactory::new()->create(['bandSpace' => $space]);
        SetlistItemFactory::new(['setlist' => $source, 'type' => SetlistItemType::Break, 'label' => 'Pause', 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->copy($space, $archived, ['from_setlist_id' => (string) $source->id]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cette setlist est archivée, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cette setlist est archivée, les modifications sont désactivées',
        ]);
    }

    public function test_no_source_is_refused(): void
    {
        [$user, $space, $target] = $this->setlist('Festival d\'été');

        $this->client->loginUser($user);
        $this->copy($space, $target, []);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'from_setlist_id',
                    'message' => 'Choisissez la setlist à copier',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'from_setlist_id: Choisissez la setlist à copier',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'from_setlist_id: Choisissez la setlist à copier',
        ]);
    }

    public function test_a_non_member_cannot_copy(): void
    {
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);
        $target = SetlistFactory::new()->create();
        $source = SetlistFactory::new()->create(['bandSpace' => $target->bandSpace]);

        $this->client->loginUser($outsider);
        $this->copy($target->bandSpace, $target, ['from_setlist_id' => (string) $source->id]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous n\'êtes pas membre de ce Band Space',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous n\'êtes pas membre de ce Band Space',
        ]);
    }

    /**
     * @return array{User, BandSpace, Setlist}
     */
    private function setlist(string $name): array
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $user])->create();

        return [$user, $space, SetlistFactory::new()->create(['bandSpace' => $space, 'name' => $name])];
    }

    /** @param array<string, mixed> $body */
    private function copy(BandSpace $space, Setlist $setlist, array $body): void
    {
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/setlists/' . $setlist->id . '/items/copy', $body, [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
    }

    /** @return list<SetlistItem> */
    private function items(Setlist $setlist): array
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(SetlistItemRepository::class)->findBy(['setlist' => $setlist->id], ['position' => 'ASC']);
    }

    private function assertUnprocessable(string $detail): void
    {
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => 422,
            'type' => '/errors/422',
            'description' => $detail,
        ]);
    }
}
