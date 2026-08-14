<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SetlistGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_collection_excludes_archived(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $active = SetlistFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Active list',
            'creationDatetime' => new \DateTime('2026-05-01T10:00:00+00:00'),
        ])->create();
        SetlistFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Archived list',
            'archiveDatetime' => new \DateTimeImmutable('2026-05-10T10:00:00+00:00'),
            'creationDatetime' => new \DateTime('2026-04-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Setlist',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $active->id,
                    '@type' => 'Setlist',
                    'id' => $active->id,
                    'band_space_id' => $bandSpace->id,
                    'name' => 'Active list',
                    'archive_datetime' => null,
                    'creation_datetime' => $active->creationDatetime->format(\DateTimeInterface::ATOM),
                    'update_datetime' => null,
                    'items' => [],
                    'total_duration_seconds' => 0,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    /**
     * archived=true swaps the list rather than widening it, so the trash shows the archived setlists
     * and nothing else. Before #761 an archived setlist was reachable from no list at all.
     */
    public function test_collection_with_archived_lists_only_the_trash(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        SetlistFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Active list',
            'creationDatetime' => new \DateTime('2026-05-01T10:00:00+00:00'),
        ])->create();
        $archived = SetlistFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Archived list',
            'archiveDatetime' => new \DateTimeImmutable('2026-05-10T10:00:00+00:00'),
            'creationDatetime' => new \DateTime('2026-04-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists?archived=true');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Setlist',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists',
            '@type' => 'Collection',
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists?archived=true',
                '@type' => 'PartialCollectionView',
            ],
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $archived->id,
                    '@type' => 'Setlist',
                    'id' => $archived->id,
                    'band_space_id' => $bandSpace->id,
                    'name' => 'Archived list',
                    'archive_datetime' => $archived->archiveDatetime->format(\DateTimeInterface::ATOM),
                    'creation_datetime' => $archived->creationDatetime->format(\DateTimeInterface::ATOM),
                    'update_datetime' => null,
                    'items' => [],
                    'total_duration_seconds' => 0,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    /**
     * The live setlist in my own band is what makes this test bite: an empty trash must come back empty
     * rather than falling back to the live list, and the other band's archived row must not leak in.
     */
    public function test_collection_with_archived_is_scoped_to_band(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();

        SetlistFactory::new([
            'bandSpace' => $myBand,
            'name' => 'My live list',
            'creationDatetime' => new \DateTime('2026-05-01T10:00:00+00:00'),
        ])->create();
        SetlistFactory::new([
            'bandSpace' => $otherBand,
            'name' => 'Their archived list',
            'archiveDatetime' => new \DateTimeImmutable('2026-05-10T10:00:00+00:00'),
            'creationDatetime' => new \DateTime('2026-04-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $myBand->id . '/setlists?archived=true');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Setlist',
            '@id' => '/api/band_spaces/' . $myBand->id . '/setlists',
            '@type' => 'Collection',
            'view' => [
                '@id' => '/api/band_spaces/' . $myBand->id . '/setlists?archived=true',
                '@type' => 'PartialCollectionView',
            ],
            'member' => [],
            'totalItems' => 0,
        ]);
    }

    public function test_collection_scoped_to_band(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();

        $mine = SetlistFactory::new([
            'bandSpace' => $myBand,
            'name' => 'Mine',
            'creationDatetime' => new \DateTime('2026-05-01T10:00:00+00:00'),
        ])->create();
        SetlistFactory::new(['bandSpace' => $otherBand, 'name' => 'Theirs'])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $myBand->id . '/setlists');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Setlist',
            '@id' => '/api/band_spaces/' . $myBand->id . '/setlists',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $myBand->id . '/setlists/' . $mine->id,
                    '@type' => 'Setlist',
                    'id' => $mine->id,
                    'band_space_id' => $myBand->id,
                    'name' => 'Mine',
                    'archive_datetime' => null,
                    'creation_datetime' => $mine->creationDatetime->format(\DateTimeInterface::ATOM),
                    'update_datetime' => null,
                    'items' => [],
                    'total_duration_seconds' => 0,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_collection_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($other);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists');

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
