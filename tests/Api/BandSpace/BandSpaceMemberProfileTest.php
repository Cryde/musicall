<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use Doctrine\Common\Collections\ArrayCollection;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A stage name and instruments describe a person rather than govern the band, so the rule here is
 * not the admin-only one the role endpoint uses: you edit your own, and an admin may edit anyone's.
 */
#[ResetDatabase]
class BandSpaceMemberProfileTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_a_member_sets_their_own_stage_name_and_instruments(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'jeremy_login']);
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $drums = InstrumentFactory::new()->asDrum()->create();
        $vocals = InstrumentFactory::new()->asBackingVocals()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->profileUrl($bandSpace->id, $membership->id),
            ['stage_name' => 'Jérémy', 'instrument_ids' => [(string) $drums->id, (string) $vocals->id]],
            self::HEADERS,
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceMember',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/members/' . $membership->id,
            '@type' => 'BandSpaceMember',
            'id' => (string) $membership->id,
            'band_space_id' => (string) $bandSpace->id,
            'user_id' => $user->id,
            'username' => 'jeremy_login',
            'role' => 'user',
            'stage_name' => 'Jérémy',
            'display_name' => 'Jérémy',
            // Ordered by name, which is the mapping's OrderBy, so a document does not reshuffle.
            'instruments' => [
                ['id' => (string) $drums->id, 'name' => 'Batterie'],
                ['id' => (string) $vocals->id, 'name' => 'Chœurs'],
            ],
            'profile_picture_url' => null,
            'creation_datetime' => $membership->creationDatetime->format(\DateTimeInterface::ATOM),
            'status' => 'active',
            'left_datetime' => null,
        ]);
    }

    /** Nothing chosen means the login name, which is the only other name we hold. */
    public function test_display_name_falls_back_to_the_username(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'kenny_login']);
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->profileUrl($bandSpace->id, $membership->id),
            ['stage_name' => '  '],
            self::HEADERS,
        );

        $this->assertResponseIsSuccessful();

        $reloaded = self::getContainer()->get(BandSpaceMembershipRepository::class)
            ->findOneByIdAndBandSpace((string) $membership->id, $bandSpace);
        // Stored as null, not as spaces: "nothing chosen" needs one representation or every
        // reader has to test for two.
        $this->assertNull($reloaded?->stageName);
        $this->assertSame('kenny_login', $reloaded?->displayName());
    }

    public function test_an_admin_edits_another_member(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create(['username' => 'admin_user']);
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $target = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $other])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            $this->profileUrl($bandSpace->id, $target->id),
            ['stage_name' => 'Kenny'],
            self::HEADERS,
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceMember',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/members/' . $target->id,
            '@type' => 'BandSpaceMember',
            'id' => (string) $target->id,
            'band_space_id' => (string) $bandSpace->id,
            'user_id' => $other->id,
            'username' => 'other_user',
            'role' => 'user',
            'stage_name' => 'Kenny',
            'display_name' => 'Kenny',
            'instruments' => [],
            'profile_picture_url' => null,
            'creation_datetime' => $target->creationDatetime->format(\DateTimeInterface::ATOM),
            'status' => 'active',
            'left_datetime' => null,
        ]);
    }

    /**
     * The rule that makes this endpoint different from the role one. Being in the band is not
     * standing to rename somebody else.
     */
    public function test_a_plain_member_cannot_edit_another_member(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $target = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $other])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'PATCH',
            $this->profileUrl($bandSpace->id, $target->id),
            ['stage_name' => 'Détourné'],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous ne pouvez modifier que votre propre profil',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous ne pouvez modifier que votre propre profil',
        ]);

        $reloaded = self::getContainer()->get(BandSpaceMembershipRepository::class)
            ->findOneByIdAndBandSpace((string) $target->id, $bandSpace);
        $this->assertNull($reloaded?->stageName);
    }

    /** Another space's membership is 404: the caller has no standing to learn that it exists. */
    public function test_a_membership_of_another_space_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $otherSpace = BandSpaceFactory::new()->create();
        $stranger = UserFactory::new()->create(['username' => 'stranger', 'email' => 'stranger@test.com']);
        $elsewhere = BandSpaceMembershipFactory::new(['bandSpace' => $otherSpace, 'user' => $stranger])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->profileUrl($bandSpace->id, $elsewhere->id),
            ['stage_name' => 'Ailleurs'],
            self::HEADERS,
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

    public function test_an_over_length_stage_name_is_refused(): void
    {
        [$user, $bandSpace, $membership] = $this->seedSelf();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->profileUrl($bandSpace->id, $membership->id),
            ['stage_name' => str_repeat('é', 61)],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'stage_name',
                    'message' => 'Le nom de scène ne peut pas dépasser 60 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
            ],
            'detail' => 'stage_name: Le nom de scène ne peut pas dépasser 60 caractères',
            'type' => '/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'title' => 'An error occurred',
            'description' => 'stage_name: Le nom de scène ne peut pas dépasser 60 caractères',
        ]);
    }

    /** An id the catalogue does not know is a 422 naming it, never a 500 from the uuid column. */
    public function test_an_unknown_instrument_id_is_refused_without_a_server_error(): void
    {
        [$user, $bandSpace, $membership] = $this->seedSelf();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->profileUrl($bandSpace->id, $membership->id),
            ['instrument_ids' => ['pas-un-uuid']],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Instrument inconnu : pas-un-uuid',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Instrument inconnu : pas-un-uuid',
        ]);
    }

    public function test_more_than_six_instruments_is_refused(): void
    {
        [$user, $bandSpace, $membership] = $this->seedSelf();
        $ids = [];
        for ($index = 0; $index < 7; $index++) {
            $ids[] = (string) InstrumentFactory::new()->create([
                'name' => 'Instrument ' . $index,
                'musicianName' => 'Musicien ' . $index,
                'slug' => 'instrument-' . $index,
            ])->id;
        }

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->profileUrl($bandSpace->id, $membership->id),
            ['instrument_ids' => $ids],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/756b1212-697c-468d-a9ad-50dd783bb169',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'instrument_ids',
                    'message' => 'Un membre ne peut pas avoir plus de 6 instruments',
                    'code' => '756b1212-697c-468d-a9ad-50dd783bb169',
                ],
            ],
            'detail' => 'instrument_ids: Un membre ne peut pas avoir plus de 6 instruments',
            'type' => '/validation_errors/756b1212-697c-468d-a9ad-50dd783bb169',
            'title' => 'An error occurred',
            'description' => 'instrument_ids: Un membre ne peut pas avoir plus de 6 instruments',
        ]);
    }

    /** Sending only one field must not clear the other. */
    public function test_a_stage_name_only_save_leaves_the_instruments_alone(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $drums = InstrumentFactory::new()->asDrum()->create();
        $membership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'instruments' => new ArrayCollection([$drums]),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->profileUrl($bandSpace->id, $membership->id),
            ['stage_name' => 'Jérémy'],
            self::HEADERS,
        );

        $this->assertResponseIsSuccessful();

        $reloaded = self::getContainer()->get(BandSpaceMembershipRepository::class)
            ->findOneByIdAndBandSpace((string) $membership->id, $bandSpace);
        $this->assertSame(['Batterie'], array_map(
            static fn ($instrument): string => $instrument->name,
            $reloaded?->instruments->toArray() ?? [],
        ));
    }

    public function test_the_update_is_recorded_in_the_activity_feed(): void
    {
        [$user, $bandSpace, $membership] = $this->seedSelf();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->profileUrl($bandSpace->id, $membership->id),
            ['stage_name' => 'Jérémy'],
            self::HEADERS,
        );
        $this->assertResponseIsSuccessful();

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Settings, $user->id);
        $this->assertCount(1, $activities);
        $this->assertSame('member_profile_updated', $activities[0]->type);
        $this->assertSame([
            'target_user_id' => $user->id,
            'target_username' => $user->username,
        ], $activities[0]->payload);
    }

    public function test_a_non_member_is_forbidden(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $this->client->loginUser($outsider);
        $this->client->jsonRequest(
            'PATCH',
            $this->profileUrl($bandSpace->id, $membership->id),
            ['stage_name' => 'Intrus'],
            self::HEADERS,
        );

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

    public function test_editing_a_profile_is_blocked_when_the_space_is_pending_deletion(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new DateTimeImmutable('+30 days'),
        ]);
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->profileUrl($bandSpace->id, $membership->id),
            ['stage_name' => 'Bloqué'],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
        ]);
    }

    /**
     * @return array{\App\Entity\User, \App\Entity\BandSpace\BandSpace, \App\Entity\BandSpace\BandSpaceMembership}
     */
    private function seedSelf(): array
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        return [$user, $bandSpace, $membership];
    }

    private function profileUrl(string $bandSpaceId, string $membershipId): string
    {
        return '/api/band_spaces/' . $bandSpaceId . '/members/' . $membershipId . '/profile';
    }
}
