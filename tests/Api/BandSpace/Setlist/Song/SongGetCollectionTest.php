<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Song;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class SongGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_collection_excludes_archived_by_default(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $active = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Active song',
            'creationDatetime' => new \DateTime('2026-05-01T10:00:00+00:00'),
        ])->create();
        SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Archived song',
            'archiveDatetime' => new \DateTimeImmutable('2026-05-10T10:00:00+00:00'),
            'creationDatetime' => new \DateTime('2026-04-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/songs');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Song',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $active->id,
                    '@type' => 'Song',
                    'id' => $active->id,
                    'band_space_id' => $bandSpace->id,
                    'title' => 'Active song',
                    'tempo' => null,
                    'tonality' => null,
                    'reference_duration' => null,
                    'notes' => null,
                    'archive_datetime' => null,
                    'creation_datetime' => $active->creationDatetime->format(\DateTimeInterface::ATOM),
                    'update_datetime' => null,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    /**
     * archived=true swaps the list rather than widening it, so the trash shows the archived titles and
     * nothing else. It replaced includeArchived, which returned the repertoire and the archive mixed
     * together: a shape no caller ever asked for and one a trash view cannot use.
     */
    public function test_get_collection_with_archived_lists_only_the_trash(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Toujours là',
            'creationDatetime' => new \DateTime('2026-05-01T10:00:00+00:00'),
        ])->create();
        $archived = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Trop tard',
            'archiveDatetime' => new \DateTimeImmutable('2026-05-10T10:00:00+00:00'),
            'creationDatetime' => new \DateTime('2026-04-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/songs?archived=true');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Song',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs',
            '@type' => 'Collection',
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs?archived=true',
                '@type' => 'PartialCollectionView',
            ],
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $archived->id,
                    '@type' => 'Song',
                    'id' => $archived->id,
                    'band_space_id' => $bandSpace->id,
                    'title' => 'Trop tard',
                    'tempo' => null,
                    'tonality' => null,
                    'reference_duration' => null,
                    'notes' => null,
                    'archive_datetime' => $archived->archiveDatetime->format(\DateTimeInterface::ATOM),
                    'creation_datetime' => $archived->creationDatetime->format(\DateTimeInterface::ATOM),
                    'update_datetime' => null,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    /**
     * The live title in my own band is what makes this test bite: an empty trash must come back empty
     * rather than falling back to the repertoire, and the other band's archived row must not leak in.
     */
    public function test_get_collection_with_archived_is_scoped_to_band(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();

        SongFactory::new([
            'bandSpace' => $myBand,
            'title' => 'My live title',
            'creationDatetime' => new \DateTime('2026-05-01T10:00:00+00:00'),
        ])->create();
        SongFactory::new([
            'bandSpace' => $otherBand,
            'title' => 'Their archived title',
            'archiveDatetime' => new \DateTimeImmutable('2026-05-10T10:00:00+00:00'),
            'creationDatetime' => new \DateTime('2026-04-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $myBand->id . '/songs?archived=true');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Song',
            '@id' => '/api/band_spaces/' . $myBand->id . '/songs',
            '@type' => 'Collection',
            'view' => [
                '@id' => '/api/band_spaces/' . $myBand->id . '/songs?archived=true',
                '@type' => 'PartialCollectionView',
            ],
            'member' => [],
            'totalItems' => 0,
        ]);
    }

    public function test_get_collection_scoped_to_band(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();

        $mine = SongFactory::new([
            'bandSpace' => $myBand,
            'title' => 'Mine',
            'creationDatetime' => new \DateTime('2026-05-01T10:00:00+00:00'),
        ])->create();
        SongFactory::new(['bandSpace' => $otherBand, 'title' => 'Theirs'])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $myBand->id . '/songs');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Song',
            '@id' => '/api/band_spaces/' . $myBand->id . '/songs',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $myBand->id . '/songs/' . $mine->id,
                    '@type' => 'Song',
                    'id' => $mine->id,
                    'band_space_id' => $myBand->id,
                    'title' => 'Mine',
                    'tempo' => null,
                    'tonality' => null,
                    'reference_duration' => null,
                    'notes' => null,
                    'archive_datetime' => null,
                    'creation_datetime' => $mine->creationDatetime->format(\DateTimeInterface::ATOM),
                    'update_datetime' => null,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_get_collection_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($other);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/songs');

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
