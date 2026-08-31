<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Absence;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\MemberAbsenceFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The item Get exists so the `@id` every absence payload advertises resolves. The other tests match
 * that `@id` as a string, which only proves the operation is declared with the right template:
 * IRI generation is template substitution and never reaches the provider. These two cases are what
 * prove the route and its authorization actually answer.
 */
#[ResetDatabase]
class MemberAbsenceGetTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_member_reads_one_absence_by_its_advertised_iri(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandMate = UserFactory::new()->create(['username' => 'band_mate', 'email' => 'band_mate@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $bandMateMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $bandMate,
            'stageName' => 'Jo la Basse',
        ])->create();
        $absence = MemberAbsenceFactory::new([
            'member' => $bandMateMembership,
            'startDate' => new DateTimeImmutable('2026-06-10'),
            'endDate' => new DateTimeImmutable('2026-06-12'),
            'reason' => 'Vacances',
        ])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MemberAbsence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/absences/' . $absence->id,
            '@type' => 'MemberAbsence',
            'id' => $absence->id,
            'band_space_id' => $bandSpace->id,
            'member_id' => $bandMateMembership->id,
            'display_name' => 'Jo la Basse',
            'profile_picture_url' => null,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'reason' => 'Vacances',
            // Somebody else's absence, and the reader is not an admin.
            'can_manage' => false,
            'creation_datetime' => $absence->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_a_non_member_cannot_read_one_absence(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'band_member', 'email' => 'band_member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $absence = MemberAbsenceFactory::new([
            'member' => $membership,
            'startDate' => new DateTimeImmutable('2026-06-10'),
            'endDate' => new DateTimeImmutable('2026-06-12'),
        ])->create();

        $this->client->loginUser($stranger);
        $this->client->request(
            'GET',
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
            'detail' => 'Vous n\'êtes pas membre de ce Band Space',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous n\'êtes pas membre de ce Band Space',
        ]);
    }
}
