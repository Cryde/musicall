<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Absence;

use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\MemberAbsenceRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\BandSpace\Agenda\ValidAbsenceRange;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\NotBlank;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class MemberAbsenceCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_member_records_their_own_absence(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            [
                'startDate' => '2026-08-10',
                'endDate' => '2026-08-12',
                'reason' => 'Vacances',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repository = self::getContainer()->get(MemberAbsenceRepository::class);
        $absences = $repository->findAll();
        $this->assertCount(1, $absences);
        $absence = $absences[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/MemberAbsence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            '@type' => 'MemberAbsence',
            'id' => $absence->id,
            'band_space_id' => $bandSpace->id,
            'member_id' => $membership->id,
            'display_name' => $user->username,
            'profile_picture_url' => null,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'reason' => 'Vacances',
            'can_manage' => true,
            'creation_datetime' => $absence->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_a_single_day_absence_is_a_range_that_starts_and_ends_the_same_day(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            ['startDate' => '2026-08-22', 'endDate' => '2026-08-22'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repository = self::getContainer()->get(MemberAbsenceRepository::class);
        $absence = $repository->findAll()[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/MemberAbsence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            '@type' => 'MemberAbsence',
            'id' => $absence->id,
            'band_space_id' => $bandSpace->id,
            'member_id' => $membership->id,
            'display_name' => $user->username,
            'profile_picture_url' => null,
            'start_date' => '2026-08-22',
            'end_date' => '2026-08-22',
            'reason' => null,
            'can_manage' => true,
            'creation_datetime' => $absence->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_an_admin_records_an_absence_for_another_member(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandMate = UserFactory::new()->create(['username' => 'band_mate', 'email' => 'band_mate@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $bandMateMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $bandMate,
            'stageName' => 'Jo la Basse',
        ])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            [
                'memberId' => (string) $bandMateMembership->id,
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-05',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repository = self::getContainer()->get(MemberAbsenceRepository::class);
        $absence = $repository->findAll()[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/MemberAbsence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            '@type' => 'MemberAbsence',
            'id' => $absence->id,
            'band_space_id' => $bandSpace->id,
            'member_id' => $bandMateMembership->id,
            'display_name' => 'Jo la Basse',
            'profile_picture_url' => null,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'reason' => null,
            'can_manage' => true,
            'creation_datetime' => $absence->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_a_member_may_name_their_own_membership_explicitly(): void
    {
        // The other half of resolveTarget: naming yourself is the same as leaving memberId out, so
        // the front end may send it unconditionally without needing to know the caller's role.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            [
                'memberId' => (string) $membership->id,
                'startDate' => '2026-08-10',
                'endDate' => '2026-08-12',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repository = self::getContainer()->get(MemberAbsenceRepository::class);
        $absence = $repository->findAll()[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/MemberAbsence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            '@type' => 'MemberAbsence',
            'id' => $absence->id,
            'band_space_id' => $bandSpace->id,
            'member_id' => $membership->id,
            'display_name' => $user->username,
            'profile_picture_url' => null,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'reason' => null,
            'can_manage' => true,
            'creation_datetime' => $absence->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_a_member_cannot_record_an_absence_for_someone_else(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandMate = UserFactory::new()->create(['username' => 'band_mate', 'email' => 'band_mate@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $bandMateMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $bandMate])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            [
                'memberId' => (string) $bandMateMembership->id,
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-05',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
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
        $this->assertCount(0, $repository->findAll());
    }

    public function test_an_admin_cannot_record_an_absence_for_a_member_who_has_left(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $formerMember = UserFactory::new()->create(['username' => 'former_member', 'email' => 'former_member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $formerMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $formerMember,
            'status' => MembershipStatus::Left,
        ])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            [
                'memberId' => (string) $formerMembership->id,
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-05',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce membre ne fait plus partie du Band Space',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Ce membre ne fait plus partie du Band Space',
        ]);
    }

    public function test_an_admin_gets_a_404_for_a_member_of_another_band_space(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $stranger = UserFactory::new()->create(['username' => 'stranger_user', 'email' => 'stranger@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $otherBandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $strangerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $otherBandSpace, 'user' => $stranger])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            [
                'memberId' => (string) $strangerMembership->id,
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-05',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Membre introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Membre introuvable',
        ]);
    }

    public function test_an_end_date_before_the_start_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            ['startDate' => '2026-08-12', 'endDate' => '2026-08-10'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidAbsenceRange::END_BEFORE_START_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'end_date',
                    'message' => 'La date de fin doit être postérieure ou égale à la date de début.',
                    'code' => ValidAbsenceRange::END_BEFORE_START_CODE,
                ],
            ],
            'detail' => 'end_date: La date de fin doit être postérieure ou égale à la date de début.',
            'type' => '/validation_errors/' . ValidAbsenceRange::END_BEFORE_START_CODE,
            'title' => 'An error occurred',
            'description' => 'end_date: La date de fin doit être postérieure ou égale à la date de début.',
        ]);
    }

    public function test_a_range_longer_than_a_year_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            ['startDate' => '2026-01-01', 'endDate' => '2027-01-02'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidAbsenceRange::RANGE_TOO_LONG_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'end_date',
                    'message' => 'Une indisponibilité ne peut pas dépasser 366 jours.',
                    'code' => ValidAbsenceRange::RANGE_TOO_LONG_CODE,
                ],
            ],
            'detail' => 'end_date: Une indisponibilité ne peut pas dépasser 366 jours.',
            'type' => '/validation_errors/' . ValidAbsenceRange::RANGE_TOO_LONG_CODE,
            'title' => 'An error occurred',
            'description' => 'end_date: Une indisponibilité ne peut pas dépasser 366 jours.',
        ]);
    }

    public function test_an_impossible_calendar_day_is_rejected_rather_than_rolling_over(): void
    {
        // This is the flip #777 left behind and #935 came back for. While the dates were typed
        // DateTimeImmutable, PHP's parser did no calendar check, so 31 February was stored as 3 March
        // and by validation time the original string was gone so nothing could tell them apart.
        // Assert\Date is a `^\d{4}-\d{2}-\d{2}$` regex plus checkdate, which is the only thing in
        // either framework that refuses the day outright.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            ['startDate' => '2026-02-31', 'endDate' => '2026-03-05'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Date::INVALID_DATE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'start_date',
                    'message' => 'Le format de la date est invalide (attendu : AAAA-MM-JJ)',
                    'code' => Date::INVALID_DATE_ERROR,
                ],
            ],
            'detail' => 'start_date: Le format de la date est invalide (attendu : AAAA-MM-JJ)',
            'type' => '/validation_errors/' . Date::INVALID_DATE_ERROR,
            'title' => 'An error occurred',
            'description' => 'start_date: Le format de la date est invalide (attendu : AAAA-MM-JJ)',
        ]);

        $repository = self::getContainer()->get(MemberAbsenceRepository::class);
        $this->assertCount(0, $repository->findAll());
    }

    public function test_a_whitespace_only_date_is_rejected_rather_than_read_as_today(): void
    {
        // DateTimeImmutable('   ') returns the current timestamp, so anything that lets this through
        // silently records an absence starting today. NotBlank does not trim, so it is Assert\Date's
        // regex that refuses the value - which is exactly the gap a hand-rolled isFilled() guard used
        // to open before #933.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            ['startDate' => '   ', 'endDate' => '2026-08-12'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Date::INVALID_FORMAT_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'start_date',
                    'message' => 'Le format de la date est invalide (attendu : AAAA-MM-JJ)',
                    'code' => Date::INVALID_FORMAT_ERROR,
                ],
            ],
            'detail' => 'start_date: Le format de la date est invalide (attendu : AAAA-MM-JJ)',
            'type' => '/validation_errors/' . Date::INVALID_FORMAT_ERROR,
            'title' => 'An error occurred',
            'description' => 'start_date: Le format de la date est invalide (attendu : AAAA-MM-JJ)',
        ]);

        $repository = self::getContainer()->get(MemberAbsenceRepository::class);
        $this->assertCount(0, $repository->findAll());
    }

    public function test_a_trailing_null_byte_is_refused_rather_than_crashing(): void
    {
        // #934's crash site. While the property was typed, DateTimeNormalizer's strict `!Y-m-d`
        // would have raised a ValueError on this byte, which is why the old comment here argued for
        // the loose parser. Both halves of the current shape have to hold: Assert\Date's regex is
        // anchored so the byte cannot match, and CalendarDay::parse uses the constructor rather than
        // createFromFormat, so ValidAbsenceRange cannot resurrect the ValueError from the other side.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            ['startDate' => "2026-08-10\0", 'endDate' => '2026-08-12'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Date::INVALID_FORMAT_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'start_date',
                    'message' => 'Le format de la date est invalide (attendu : AAAA-MM-JJ)',
                    'code' => Date::INVALID_FORMAT_ERROR,
                ],
            ],
            'detail' => 'start_date: Le format de la date est invalide (attendu : AAAA-MM-JJ)',
            'type' => '/validation_errors/' . Date::INVALID_FORMAT_ERROR,
            'title' => 'An error occurred',
            'description' => 'start_date: Le format de la date est invalide (attendu : AAAA-MM-JJ)',
        ]);

        $repository = self::getContainer()->get(MemberAbsenceRepository::class);
        $this->assertCount(0, $repository->findAll());
    }

    public function test_an_omitted_start_date_is_reported_rather_than_left_uninitialized(): void
    {
        // The non-nullable string property stays uninitialized, which the class constraint has to
        // survive without touching it. Symfony's PropertyMetadata reads an uninitialized typed
        // property as null, which is what gives NotBlank something to fire on.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            ['endDate' => '2026-08-12'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'start_date',
                    'message' => 'Veuillez spécifier une date de début',
                    'code' => NotBlank::IS_BLANK_ERROR,
                ],
            ],
            'detail' => 'start_date: Veuillez spécifier une date de début',
            'type' => '/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            'title' => 'An error occurred',
            'description' => 'start_date: Veuillez spécifier une date de début',
        ]);

        $repository = self::getContainer()->get(MemberAbsenceRepository::class);
        $this->assertCount(0, $repository->findAll());
    }

    public function test_a_non_member_cannot_record_an_absence(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();

        $this->client->loginUser($stranger);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            ['startDate' => '2026-08-10', 'endDate' => '2026-08-12'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

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
}
