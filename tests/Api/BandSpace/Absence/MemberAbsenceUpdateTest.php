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
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class MemberAbsenceUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_member_moves_their_own_absence(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $absence = MemberAbsenceFactory::new([
            'member' => $membership,
            'startDate' => new DateTimeImmutable('2026-06-10'),
            'endDate' => new DateTimeImmutable('2026-06-12'),
            'reason' => 'Vacances',
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            ['startDate' => '2026-06-15', 'endDate' => '2026-06-18', 'reason' => 'Déménagement'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MemberAbsence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            '@type' => 'MemberAbsence',
            'id' => $absence->id,
            'band_space_id' => $bandSpace->id,
            'member_id' => $membership->id,
            'display_name' => $user->username,
            'profile_picture_url' => null,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-18',
            'reason' => 'Déménagement',
            'can_manage' => true,
            'creation_datetime' => $absence->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_clearing_the_reason_is_a_real_patch_and_not_a_no_op(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $absence = MemberAbsenceFactory::new([
            'member' => $membership,
            'startDate' => new DateTimeImmutable('2026-06-10'),
            'endDate' => new DateTimeImmutable('2026-06-12'),
            'reason' => 'Vacances',
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            ['reason' => null],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MemberAbsence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            '@type' => 'MemberAbsence',
            'id' => $absence->id,
            'band_space_id' => $bandSpace->id,
            'member_id' => $membership->id,
            'display_name' => $user->username,
            'profile_picture_url' => null,
            // The dates were not in the payload, so they are untouched.
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'reason' => null,
            'can_manage' => true,
            'creation_datetime' => $absence->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_an_admin_edits_another_member_absence(): void
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
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            ['endDate' => '2026-06-14'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MemberAbsence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            '@type' => 'MemberAbsence',
            'id' => $absence->id,
            'band_space_id' => $bandSpace->id,
            'member_id' => $bandMateMembership->id,
            'display_name' => $bandMate->username,
            'profile_picture_url' => null,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-14',
            'reason' => null,
            'can_manage' => true,
            'creation_datetime' => $absence->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_a_member_cannot_edit_another_member_absence(): void
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
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            ['endDate' => '2026-06-14'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
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
        $this->assertSame('2026-06-12', $repository->findAll()[0]->endDate->format('Y-m-d'));
    }

    public function test_patching_one_date_is_still_validated_against_the_stored_other_one(): void
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
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            ['endDate' => '2026-06-01'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . GreaterThanOrEqual::TOO_LOW_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'end_date',
                    'message' => 'La date de fin doit être postérieure ou égale à la date de début.',
                    'code' => GreaterThanOrEqual::TOO_LOW_ERROR,
                ],
            ],
            'detail' => 'end_date: La date de fin doit être postérieure ou égale à la date de début.',
            'type' => '/validation_errors/' . GreaterThanOrEqual::TOO_LOW_ERROR,
            'title' => 'An error occurred',
            'description' => 'end_date: La date de fin doit être postérieure ou égale à la date de début.',
        ]);
    }

    public function test_a_patch_cannot_move_an_absence_to_another_member(): void
    {
        // The processor never reads memberId, so an absence stays with whoever it was recorded for:
        // moving one is a delete plus a create. Asserted rather than left to the docblock, because
        // this is the one authorization rule in the module with no failing path of its own - a
        // reassignment would simply be accepted and silently ignored, or silently applied.
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandMate = UserFactory::new()->create(['username' => 'band_mate', 'email' => 'band_mate@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $adminMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $admin,
            'role' => Role::Admin,
        ])->create();
        $bandMateMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $bandMate])->create();
        $absence = MemberAbsenceFactory::new([
            'member' => $bandMateMembership,
            'startDate' => new DateTimeImmutable('2026-06-10'),
            'endDate' => new DateTimeImmutable('2026-06-12'),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            ['memberId' => (string) $adminMembership->id, 'endDate' => '2026-06-14'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MemberAbsence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            '@type' => 'MemberAbsence',
            'id' => $absence->id,
            'band_space_id' => $bandSpace->id,
            // Still the band mate's, not the admin's, even though the payload named the admin.
            'member_id' => $bandMateMembership->id,
            'display_name' => $bandMate->username,
            'profile_picture_url' => null,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-14',
            'reason' => null,
            'can_manage' => true,
            'creation_datetime' => $absence->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $repository = self::getContainer()->get(MemberAbsenceRepository::class);
        $this->assertSame((string) $bandMateMembership->id, (string) $repository->findAll()[0]->member->id);
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
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            ['endDate' => '2026-06-14'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
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
    }
}
