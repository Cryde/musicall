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
class TechRiderGetItemTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_tech_rider(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Technical rider 2026',
            'createdBy' => $user,
            'creationDatetime' => new DateTime('2026-01-15T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRider',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id,
            '@type' => 'TechRider',
            'id' => $rider->id,
            'band_space_id' => $bandSpace->id,
            'name' => 'Technical rider 2026',
            'created_by_username' => $user->username,
            'archive_datetime' => null,
            'creation_datetime' => $rider->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
            'items' => [],
            'item_count' => 0,
        ]);
    }

    /**
     * An archived rider stays readable so it can be restored or, later, duplicated
     * into the next tour's rider.
     */
    public function test_get_archived_tech_rider_is_readable(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Technical rider 2025',
            'createdBy' => $user,
            'creationDatetime' => new DateTime('2025-01-15T10:00:00+00:00'),
            'archiveDatetime' => new DateTimeImmutable('2026-01-05T09:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRider',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id,
            '@type' => 'TechRider',
            'id' => $rider->id,
            'band_space_id' => $bandSpace->id,
            'name' => 'Technical rider 2025',
            'created_by_username' => $user->username,
            'archive_datetime' => $rider->archiveDatetime?->format(DateTimeInterface::ATOM),
            'creation_datetime' => $rider->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
            'items' => [],
            'item_count' => 0,
        ]);
    }

    public function test_get_tech_rider_from_another_band_space_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();

        $theirs = TechRiderFactory::new(['bandSpace' => $otherBand, 'name' => 'Theirs'])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $myBand->id . '/tech_riders/' . $theirs->id);

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

    /**
     * The id column is a uuid, so a non-uuid lookup is only safe as long as the repository
     * keeps binding it as a plain string through the query builder. Switching to find() or
     * findOneBy() would bind through the uuid type and throw ValueNotConvertible, turning
     * this 404 into a 500. This test is what catches that.
     */
    public function test_get_tech_rider_with_a_non_uuid_id_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/tech_riders/not-a-uuid');

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
}
