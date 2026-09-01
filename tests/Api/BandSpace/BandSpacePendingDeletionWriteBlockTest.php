<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use App\Enum\BandSpace\InvitationStatus;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Repository\BandSpace\MemberAbsenceRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceInvitationFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A space pending deletion is read only for the whole grace period.
 *
 * The rule reaches roughly 75 processors through BandSpaceMemberChecker::checkMemberForWrite() and
 * BandSpaceAdminChecker::checkAdminForWrite(), so this covers one representative per wiring rather than
 * every endpoint. BandSpaceWriteGuardCoverageTest is what proves no processor escapes the wiring.
 */
#[ResetDatabase]
class BandSpacePendingDeletionWriteBlockTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    private const array EXPECTED_CONFLICT = [
        '@context' => '/api/contexts/Error',
        '@id' => '/api/errors/409',
        '@type' => 'Error',
        'title' => 'An error occurred',
        'detail' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
        'status' => 409,
        'type' => '/errors/409',
        'description' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
    ];

    /**
     * Representative of the 61 processors on checkMemberForWrite().
     */
    public function test_a_member_cannot_create_a_task(): void
    {
        [$admin, $bandSpace] = $this->pendingDeletionSpace();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks',
            ['title' => 'Répéter le nouveau morceau'],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals(self::EXPECTED_CONFLICT);
    }

    /**
     * Representative of the 7 processors on checkAdminForWrite().
     */
    public function test_an_admin_cannot_invite_anyone(): void
    {
        [$admin, $bandSpace] = $this->pendingDeletionSpace();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/invitations',
            ['identifier' => 'newuser@example.com'],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals(self::EXPECTED_CONFLICT);

        $this->assertCount(
            0,
            self::getContainer()->get(BandSpaceInvitationRepository::class)->findBy(['bandSpace' => $bandSpace->id]),
        );
    }

    /**
     * The three note processors used to inline their own membership check and bypass the checkers entirely.
     */
    public function test_a_member_cannot_create_a_note(): void
    {
        [$admin, $bandSpace] = $this->pendingDeletionSpace();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes',
            ['title' => 'Idées pour le prochain album'],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals(self::EXPECTED_CONFLICT);

        $this->assertCount(0, self::getContainer()->get(BandSpaceNoteRepository::class)->findByBandSpace($bandSpace));
    }

    public function test_a_member_cannot_record_an_absence(): void
    {
        [$admin, $bandSpace] = $this->pendingDeletionSpace();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/absences',
            ['startDate' => '2026-08-10', 'endDate' => '2026-08-12'],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals(self::EXPECTED_CONFLICT);

        $this->assertCount(0, self::getContainer()->get(MemberAbsenceRepository::class)->findAll());
    }

    /**
     * Joining a condemned space is pointless, and this processor has no checker to hang the guard on, so it
     * gets its own message.
     */
    public function test_an_invitee_cannot_accept_an_invitation_to_a_condemned_space(): void
    {
        [$admin, $bandSpace] = $this->pendingDeletionSpace();
        $invitee = UserFactory::new()->create(['username' => 'invitee', 'email' => 'invitee@example.com']);

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invitee@example.com',
            'existingUser' => $invitee,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($invitee);
        $this->client->request(
            'POST',
            '/api/band_spaces/invitations/' . $invitation->token . '/accept',
            [],
            [],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet espace est en attente de suppression, vous ne pouvez pas le rejoindre',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cet espace est en attente de suppression, vous ne pouvez pas le rejoindre',
        ]);

        $this->assertFalse(
            self::getContainer()->get(BandSpaceMembershipRepository::class)->isMember($bandSpace, $invitee),
        );
    }

    /**
     * The guard runs after the membership check on purpose. A stranger must not be able to learn that a
     * space is pending deletion by probing a write endpoint.
     */
    public function test_a_non_member_still_gets_403_and_never_learns_the_space_is_condemned(): void
    {
        [, $bandSpace] = $this->pendingDeletionSpace();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@example.com']);

        $this->client->loginUser($outsider);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks',
            ['title' => 'Sonder cet espace'],
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function readableModuleProvider(): iterable
    {
        yield 'tasks' => ['tasks'];
        yield 'notes' => ['notes'];
        yield 'files' => ['files'];
        yield 'agenda' => ['agenda-entries'];
        yield 'setlists' => ['setlists'];
        yield 'finance categories' => ['finance/categories'];
        yield 'tech riders' => ['tech_riders'];
        yield 'absences' => ['absences'];
    }

    /**
     * The window exists so members can retrieve their work, so reads stay open. One test per module because
     * loginUser() only survives a single request. The download path has its own case in
     * BandSpaceFileDownloadTest, where the storage fixtures live.
     */
    #[DataProvider('readableModuleProvider')]
    public function test_reads_stay_open(string $module): void
    {
        [$admin, $bandSpace] = $this->pendingDeletionSpace();

        $this->client->loginUser($admin);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/' . $module,
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
    }

    public function test_an_admin_can_still_cancel_the_deletion(): void
    {
        [$admin, $bandSpace] = $this->pendingDeletionSpace();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/band_spaces/' . $bandSpace->id . '/restore', [], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertNull(
            self::getContainer()->get(BandSpaceRepository::class)
                ->findOneByIdWithMemberships((string) $bandSpace->id)
                ?->deletionScheduledDatetime,
        );
    }

    /**
     * Leaving refuses to strand a space without an admin, so the sole admin has to promote a successor first.
     * Blocking role changes during the grace period would trap them: unable to promote, unable to leave. And
     * letting them leave without a successor is not the alternative, that would strand the space with nobody
     * able to cancel the deletion.
     *
     * This half proves the promotion is still allowed. The next one proves the departure that follows it.
     */
    public function test_the_sole_admin_can_still_promote_a_successor(): void
    {
        [$admin, $bandSpace] = $this->pendingDeletionSpace();
        $member = UserFactory::new()->create(['username' => 'successor', 'email' => 'successor@example.com']);
        $membership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $member,
            'role' => Role::User,
        ])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/members/' . $membership->id,
            ['role' => 'admin'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            Role::Admin,
            self::getContainer()->get(BandSpaceMembershipRepository::class)->findMembership($bandSpace, $member)?->role,
        );
    }

    public function test_the_departing_admin_can_leave_once_a_successor_exists(): void
    {
        [$admin, $bandSpace] = $this->pendingDeletionSpace();
        $successor = UserFactory::new()->create(['username' => 'successor', 'email' => 'successor@example.com']);
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $successor,
            'role' => Role::Admin,
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/band_spaces/' . $bandSpace->id . '/leave', [], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // An admin remains, so the deletion stays cancellable.
        $membershipRepository = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertFalse($membershipRepository->isMember($bandSpace, $admin));
        $this->assertSame(Role::Admin, $membershipRepository->findMembership($bandSpace, $successor)?->role);
    }

    public function test_a_member_can_still_leave(): void
    {
        [$admin, $bandSpace] = $this->pendingDeletionSpace();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@example.com']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($member);
        $this->client->request('POST', '/api/band_spaces/' . $bandSpace->id . '/leave', [], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $membershipRepository = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertFalse($membershipRepository->isMember($bandSpace, $member));
        // The admin is untouched, only the leaving member's own membership changed.
        $this->assertTrue($membershipRepository->isMember($bandSpace, $admin));
    }

    public function test_an_invitee_can_still_decline(): void
    {
        [$admin, $bandSpace] = $this->pendingDeletionSpace();
        $invitee = UserFactory::new()->create(['username' => 'invitee', 'email' => 'invitee@example.com']);

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invitee@example.com',
            'existingUser' => $invitee,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($invitee);
        $this->client->request(
            'POST',
            '/api/band_spaces/invitations/' . $invitation->token . '/decline',
            [],
            [],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame(
            InvitationStatus::Declined,
            self::getContainer()->get(BandSpaceInvitationRepository::class)->find($invitation->id)?->status,
        );
    }

    /**
     * @return array{User, BandSpace}
     */
    private function pendingDeletionSpace(): array
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create([
            'name' => 'Groupe condamné',
            'deletionScheduledDatetime' => new \DateTimeImmutable('+30 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        return [$admin, $bandSpace];
    }
}
