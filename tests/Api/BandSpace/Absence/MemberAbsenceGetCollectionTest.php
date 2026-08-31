<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Absence;

use App\Enum\BandSpace\Role;
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
class MemberAbsenceGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_member_sees_every_member_absence_in_the_window(): void
    {
        // Shared visibility is the whole point of the feature: a band that hides availability from
        // itself gains nothing, so a plain member reads the other members' rows too.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandMate = UserFactory::new()->create(['username' => 'band_mate', 'email' => 'band_mate@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $bandMateMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $bandMate,
            'stageName' => 'Jo la Basse',
        ])->create();

        $mine = MemberAbsenceFactory::new([
            'member' => $membership,
            'startDate' => new DateTimeImmutable('2026-06-10'),
            'endDate' => new DateTimeImmutable('2026-06-12'),
            'reason' => 'Vacances',
        ])->create();
        $theirs = MemberAbsenceFactory::new([
            'member' => $bandMateMembership,
            'startDate' => new DateTimeImmutable('2026-06-20'),
            'endDate' => new DateTimeImmutable('2026-06-20'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/absences?from=2026-06-01&to=2026-06-30',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MemberAbsence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences/' . $mine->id,
                    '@type' => 'MemberAbsence',
                    'id' => $mine->id,
                    'band_space_id' => $bandSpace->id,
                    'member_id' => $membership->id,
                    'display_name' => $user->username,
                    'profile_picture_url' => null,
                    'start_date' => '2026-06-10',
                    'end_date' => '2026-06-12',
                    'reason' => 'Vacances',
                    'can_manage' => true,
                    'creation_datetime' => $mine->creationDatetime->format(\DateTimeInterface::ATOM),
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences/' . $theirs->id,
                    '@type' => 'MemberAbsence',
                    'id' => $theirs->id,
                    'band_space_id' => $bandSpace->id,
                    'member_id' => $bandMateMembership->id,
                    'display_name' => 'Jo la Basse',
                    'profile_picture_url' => null,
                    'start_date' => '2026-06-20',
                    'end_date' => '2026-06-20',
                    'reason' => null,
                    // Somebody else's absence: readable, not editable.
                    'can_manage' => false,
                    'creation_datetime' => $theirs->creationDatetime->format(\DateTimeInterface::ATOM),
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences?from=2026-06-01&to=2026-06-30',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_an_admin_may_manage_every_absence(): void
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
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/absences?from=2026-06-01&to=2026-06-30',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MemberAbsence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
                    '@type' => 'MemberAbsence',
                    'id' => $absence->id,
                    'band_space_id' => $bandSpace->id,
                    'member_id' => $bandMateMembership->id,
                    'display_name' => $bandMate->username,
                    'profile_picture_url' => null,
                    'start_date' => '2026-06-10',
                    'end_date' => '2026-06-12',
                    'reason' => null,
                    'can_manage' => true,
                    'creation_datetime' => $absence->creationDatetime->format(\DateTimeInterface::ATOM),
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences?from=2026-06-01&to=2026-06-30',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_the_window_keeps_absences_that_only_touch_its_edge_and_drops_the_rest(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // Ends on the first day asked for: still overlaps, so it is in.
        $touchingStart = MemberAbsenceFactory::new([
            'member' => $membership,
            'startDate' => new DateTimeImmutable('2026-05-28'),
            'endDate' => new DateTimeImmutable('2026-06-01'),
        ])->create();
        // Starts on the last day asked for: same.
        $touchingEnd = MemberAbsenceFactory::new([
            'member' => $membership,
            'startDate' => new DateTimeImmutable('2026-06-30'),
            'endDate' => new DateTimeImmutable('2026-07-04'),
        ])->create();
        // Entirely before and entirely after: out.
        MemberAbsenceFactory::new([
            'member' => $membership,
            'startDate' => new DateTimeImmutable('2026-05-20'),
            'endDate' => new DateTimeImmutable('2026-05-31'),
        ])->create();
        MemberAbsenceFactory::new([
            'member' => $membership,
            'startDate' => new DateTimeImmutable('2026-07-01'),
            'endDate' => new DateTimeImmutable('2026-07-10'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/absences?from=2026-06-01&to=2026-06-30',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MemberAbsence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences/' . $touchingStart->id,
                    '@type' => 'MemberAbsence',
                    'id' => $touchingStart->id,
                    'band_space_id' => $bandSpace->id,
                    'member_id' => $membership->id,
                    'display_name' => $user->username,
                    'profile_picture_url' => null,
                    'start_date' => '2026-05-28',
                    'end_date' => '2026-06-01',
                    'reason' => null,
                    'can_manage' => true,
                    'creation_datetime' => $touchingStart->creationDatetime->format(\DateTimeInterface::ATOM),
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences/' . $touchingEnd->id,
                    '@type' => 'MemberAbsence',
                    'id' => $touchingEnd->id,
                    'band_space_id' => $bandSpace->id,
                    'member_id' => $membership->id,
                    'display_name' => $user->username,
                    'profile_picture_url' => null,
                    'start_date' => '2026-06-30',
                    'end_date' => '2026-07-04',
                    'reason' => null,
                    'can_manage' => true,
                    'creation_datetime' => $touchingEnd->creationDatetime->format(\DateTimeInterface::ATOM),
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences?from=2026-06-01&to=2026-06-30',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_another_band_space_absences_never_leak(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $stranger = UserFactory::new()->create(['username' => 'stranger_user', 'email' => 'stranger@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $otherBandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $strangerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $otherBandSpace, 'user' => $stranger])->create();

        MemberAbsenceFactory::new([
            'member' => $strangerMembership,
            'startDate' => new DateTimeImmutable('2026-06-10'),
            'endDate' => new DateTimeImmutable('2026-06-12'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/absences?from=2026-06-01&to=2026-06-30',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MemberAbsence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences?from=2026-06-01&to=2026-06-30',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_an_unparseable_window_bound_is_a_bad_request(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/absences?from=not-a-date',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Date invalide',
            'status' => 400,
            'type' => '/errors/400',
            'description' => 'Date invalide',
        ]);
    }

    public function test_a_non_member_cannot_read_the_absences(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();

        $this->client->loginUser($stranger);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
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
