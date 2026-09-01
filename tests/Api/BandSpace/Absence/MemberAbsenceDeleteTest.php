<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Absence;

use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\MemberAbsenceRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\MemberAbsenceFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class MemberAbsenceDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_member_deletes_their_own_absence(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $absence = MemberAbsenceFactory::new([
            'member' => $membership,
            'startDate' => new DateTimeImmutable('2026-06-10'),
            'endDate' => new DateTimeImmutable('2026-06-12'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $repository = self::getContainer()->get(MemberAbsenceRepository::class);
        $this->assertCount(0, $repository->findAll());
    }

    public function test_an_admin_deletes_another_member_absence(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandMate = UserFactory::new()->create(['username' => 'band_mate', 'email' => 'band_mate@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $bandMateMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $bandMate])->create();
        $absence = MemberAbsenceFactory::new([
            'member' => $bandMateMembership,
            'startDate' => new DateTimeImmutable('2026-06-10'),
            'endDate' => new DateTimeImmutable('2026-06-12'),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $repository = self::getContainer()->get(MemberAbsenceRepository::class);
        $this->assertCount(0, $repository->findAll());
    }

    public function test_a_member_cannot_delete_another_member_absence(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandMate = UserFactory::new()->create(['username' => 'band_mate', 'email' => 'band_mate@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $bandMateMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $bandMate])->create();
        $absence = MemberAbsenceFactory::new([
            'member' => $bandMateMembership,
            'startDate' => new DateTimeImmutable('2026-06-10'),
            'endDate' => new DateTimeImmutable('2026-06-12'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous ne pouvez gérer que vos propres indisponibilités',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous ne pouvez gérer que vos propres indisponibilités',
        ]);

        $repository = self::getContainer()->get(MemberAbsenceRepository::class);
        $this->assertCount(1, $repository->findAll());
    }

    public function test_an_absence_of_another_band_space_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $stranger = UserFactory::new()->create(['username' => 'stranger_user', 'email' => 'stranger@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $otherBandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $strangerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $otherBandSpace, 'user' => $stranger])->create();
        $absence = MemberAbsenceFactory::new([
            'member' => $strangerMembership,
            'startDate' => new DateTimeImmutable('2026-06-10'),
            'endDate' => new DateTimeImmutable('2026-06-12'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Indisponibilité introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Indisponibilité introuvable',
        ]);

        $repository = self::getContainer()->get(MemberAbsenceRepository::class);
        $this->assertCount(1, $repository->findAll());
    }
}
