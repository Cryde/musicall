<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\User\UserFactory;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class TechRiderGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_collection_lists_live_riders_newest_first(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $older = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Technical rider 2025',
            'createdBy' => $user,
            'creationDatetime' => new DateTime('2025-01-15T10:00:00+00:00'),
        ])->create();
        $newer = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Technical rider 2026',
            'createdBy' => $user,
            'creationDatetime' => new DateTime('2026-01-15T10:00:00+00:00'),
        ])->create();
        TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Archived rider',
            'creationDatetime' => new DateTime('2026-02-15T10:00:00+00:00'),
            'archiveDatetime' => new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/tech_riders');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRider',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $newer->id,
                    '@type' => 'TechRider',
                    'id' => $newer->id,
                    'band_space_id' => $bandSpace->id,
                    'name' => 'Technical rider 2026',
                    'created_by_username' => $user->username,
                    'archive_datetime' => null,
                    'creation_datetime' => $newer->creationDatetime->format(DateTimeInterface::ATOM),
                    'update_datetime' => null,
                    'section_count' => 0,
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $older->id,
                    '@type' => 'TechRider',
                    'id' => $older->id,
                    'band_space_id' => $bandSpace->id,
                    'name' => 'Technical rider 2025',
                    'created_by_username' => $user->username,
                    'archive_datetime' => null,
                    'creation_datetime' => $older->creationDatetime->format(DateTimeInterface::ATOM),
                    'update_datetime' => null,
                    'section_count' => 0,
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_collection_archived_true_lists_only_the_archive(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Live rider',
            'creationDatetime' => new DateTime('2026-01-15T10:00:00+00:00'),
        ])->create();
        $archived = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Archived rider',
            'creationDatetime' => new DateTime('2025-01-15T10:00:00+00:00'),
            'archiveDatetime' => new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/tech_riders?archived=true');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRider',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $archived->id,
                    '@type' => 'TechRider',
                    'id' => $archived->id,
                    'band_space_id' => $bandSpace->id,
                    'name' => 'Archived rider',
                    'created_by_username' => null,
                    'archive_datetime' => $archived->archiveDatetime?->format(DateTimeInterface::ATOM),
                    'creation_datetime' => $archived->creationDatetime->format(DateTimeInterface::ATOM),
                    'update_datetime' => null,
                    'section_count' => 0,
                ],
            ],
            'totalItems' => 1,
            // Present only because the request carries a query string.
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders?archived=true',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_collection_scoped_to_the_band_space(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();

        $mine = TechRiderFactory::new([
            'bandSpace' => $myBand,
            'name' => 'Mine',
            'creationDatetime' => new DateTime('2026-01-15T10:00:00+00:00'),
        ])->create();
        TechRiderFactory::new(['bandSpace' => $otherBand, 'name' => 'Theirs'])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $myBand->id . '/tech_riders');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRider',
            '@id' => '/api/band_spaces/' . $myBand->id . '/tech_riders',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $myBand->id . '/tech_riders/' . $mine->id,
                    '@type' => 'TechRider',
                    'id' => $mine->id,
                    'band_space_id' => $myBand->id,
                    'name' => 'Mine',
                    'created_by_username' => null,
                    'archive_datetime' => null,
                    'creation_datetime' => $mine->creationDatetime->format(DateTimeInterface::ATOM),
                    'update_datetime' => null,
                    'section_count' => 0,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_collection_not_member(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $this->client->loginUser($outsider);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/tech_riders');

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
